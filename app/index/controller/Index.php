<?php

namespace app\index\controller;

use app\service\SafeHttpClient;
use think\facade\Db;
use think\facade\Session;

class Index
{
    private const APP_TIMESTAMP_WINDOW_MS = 120000;

    public function index()
    {
        return redirect('/index.html');
    }

    public function getReturn(int $code = 1, string $msg = '成功', $data = null): array
    {
        return ['code' => $code, 'msg' => $msg, 'data' => $data];
    }

    public function login()
    {
        $user = (string) input('user', '');
        $pass = (string) input('pass', '');
        $storedUser = $this->setting('user');
        $storedPass = $this->setting('pass');

        if (!hash_equals($storedUser, $user) || !$this->verifyPassword($pass, $storedPass)) {
            return json($this->getReturn(-1, '账号或密码错误'));
        }
        if (!password_get_info($storedPass)['algo']) {
            Db::name('setting')->where('vkey', 'pass')->update(['vvalue' => password_hash($pass, PASSWORD_DEFAULT)]);
        }

        Session::set('admin', 1);
        Session::regenerate(true);

        return json($this->getReturn());
    }

    public function logout()
    {
        Session::clear();
        return json($this->getReturn());
    }

    public function getMenu()
    {
        if (!Session::has('admin')) {
            return json($this->getReturn(-1, '没有登录'));
        }

        return json([
            ['name' => '系统设置', 'type' => 'url', 'url' => 'admin/setting.html?t=' . time()],
            ['name' => '监控端设置', 'type' => 'url', 'url' => 'admin/jk.html?t=' . time()],
            ['name' => '微信二维码', 'type' => 'menu', 'node' => [
                ['name' => '添加', 'type' => 'url', 'url' => 'admin/addwxqrcode.html?t=' . time()],
                ['name' => '管理', 'type' => 'url', 'url' => 'admin/wxqrcodelist.html?t=' . time()],
            ]],
            ['name' => '支付宝二维码', 'type' => 'menu', 'node' => [
                ['name' => '添加', 'type' => 'url', 'url' => 'admin/addzfbqrcode.html?t=' . time()],
                ['name' => '管理', 'type' => 'url', 'url' => 'admin/zfbqrcodelist.html?t=' . time()],
            ]],
            ['name' => '订单列表', 'type' => 'url', 'url' => 'admin/orderlist.html?t=' . time()],
            ['name' => 'API文档', 'type' => 'url', 'url' => 'api.html?t=' . time()],
        ]);
    }

    public function createOrder()
    {
        $this->closeEndOrder(true);

        $rawPayId = (string) input('payId', '');
        $payId = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($rawPayId, 0, 100));
        if ($payId === '' || $payId !== $rawPayId) {
            return json($this->getReturn(-1, '商户订单号仅允许字母、数字、下划线和短横线'));
        }

        $type = (int) input('type', 0);
        if (!in_array($type, [1, 2], true)) {
            return json($this->getReturn(-1, '支付方式错误=>1|微信 2|支付宝'));
        }

        $priceInput = trim((string) input('price', ''));
        if (!preg_match('/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/', $priceInput) || bccomp($priceInput, '0', 2) <= 0) {
            return json($this->getReturn(-1, '订单金额必须大于0且最多两位小数'));
        }
        $priceForSign = (float) $priceInput;
        $price = number_format($priceForSign, 2, '.', '');

        $param = htmlspecialchars(substr((string) input('param', ''), 0, 500), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $key = $this->setting('key');
        $expectedSign = md5($payId . $param . $type . $priceForSign . $key);
        if (!$this->validSignature($expectedSign, (string) input('sign', ''))) {
            return json($this->getReturn(-1, '签名错误'));
        }
        if ($key === '') {
            return json($this->getReturn(-1, '请先配置通讯密钥'));
        }
        if ($this->setting('jkstate') !== '1') {
            return json($this->getReturn(-1, '监控端状态异常，请检查'));
        }
        if (Db::name('pay_order')->where('pay_id', $payId)->find()) {
            return json($this->getReturn(-1, '商户订单号已存在'));
        }

        $payUrl = $this->setting($type === 1 ? 'wxpay' : 'zfbpay');
        if ($payUrl === '') {
            return json($this->getReturn(-1, '请先进入后台配置收款码'));
        }

        [$notifyUrl, $returnUrl] = $this->orderUrls();
        if (!SafeHttpClient::isPublicUrl($notifyUrl)) {
            return json($this->getReturn(-1, '请先配置可公网访问的 HTTP/HTTPS 回调地址'));
        }
        if ($returnUrl !== '' && !$this->isHttpUrl($returnUrl)) {
            return json($this->getReturn(-1, '同步跳转地址格式错误'));
        }

        $orderId = date('YmdHis') . '-' . bin2hex(random_bytes(6));
        $reallyPriceCents = (int) bcmul($price, '100', 0);
        $direction = (int) $this->setting('payQf') === 2 ? -1 : 1;
        $reserved = false;

        Db::startTrans();
        try {
            for ($i = 0; $i < 10; $i++) {
                $reservationKey = $reallyPriceCents . '-' . $type;
                if (Db::execute('INSERT IGNORE INTO tmp_price (price, oid) VALUES (?, ?)', [$reservationKey, $orderId]) === 1) {
                    $reserved = true;
                    break;
                }
                $reallyPriceCents += $direction;
            }
            if (!$reserved || $reallyPriceCents <= 0) {
                throw new \RuntimeException('订单超出负荷，请稍后重试');
            }

            $reallyPrice = bcdiv((string) $reallyPriceCents, '100', 2);
            $fixedCode = Db::name('pay_qrcode')->where('price', $reallyPrice)->where('type', $type)->find();
            $isAuto = $fixedCode ? 0 : 1;
            if ($fixedCode) {
                $payUrl = $fixedCode['pay_url'];
            }

            $createDate = time();
            Db::name('pay_order')->insert([
                'close_date' => 0,
                'create_date' => $createDate,
                'is_auto' => $isAuto,
                'notify_url' => $notifyUrl,
                'notify_attempts' => 0,
                'next_notify_date' => 0,
                'last_notify_error' => '',
                'order_id' => $orderId,
                'param' => $param,
                'pay_date' => 0,
                'pay_id' => $payId,
                'pay_url' => $payUrl,
                'price' => $price,
                'really_price' => $reallyPrice,
                'return_url' => $returnUrl,
                'state' => 0,
                'type' => $type,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $message = $e instanceof \RuntimeException ? $e->getMessage() : '创建订单失败，请稍后重试';
            return json($this->getReturn(-1, $message));
        }

        $result = [
            'payId' => $payId,
            'orderId' => $orderId,
            'payType' => $type,
            'price' => $price,
            'reallyPrice' => $reallyPrice,
            'payUrl' => $payUrl,
            'isAuto' => $isAuto,
            'state' => 0,
            'timeOut' => $this->setting('close'),
            'date' => $createDate,
        ];

        if ((int) input('isHtml', 0) === 1) {
            return redirect('payPage/pay.html?orderId=' . rawurlencode($orderId));
        }
        return json($this->getReturn(1, '成功', $result));
    }

    public function getOrder()
    {
        $order = Db::name('pay_order')->where('order_id', (string) input('orderId', ''))->find();
        if (!$order) {
            return json($this->getReturn(-1, '云端订单编号不存在'));
        }

        return json($this->getReturn(1, '成功', [
            'payId' => $order['pay_id'],
            'orderId' => $order['order_id'],
            'payType' => $order['type'],
            'price' => $order['price'],
            'reallyPrice' => $order['really_price'],
            'payUrl' => $order['pay_url'],
            'isAuto' => $order['is_auto'],
            'state' => $order['state'],
            'timeOut' => $this->setting('close'),
            'date' => $order['create_date'],
        ]));
    }

    public function checkOrder()
    {
        $order = Db::name('pay_order')->where('order_id', (string) input('orderId', ''))->find();
        if (!$order) {
            return json($this->getReturn(-1, '云端订单编号不存在'));
        }
        if ((int) $order['state'] === 0) {
            return json($this->getReturn(-1, '订单未支付'));
        }
        if ((int) $order['state'] === -1) {
            return json($this->getReturn(-1, '订单已过期'));
        }

        $url = $this->appendOrderQuery((string) $order['return_url'], $order);
        return json($this->getReturn(1, '成功', $url));
    }

    public function closeOrder()
    {
        $orderId = (string) input('orderId', '');
        if (!$this->validSignature(md5($orderId . $this->setting('key')), (string) input('sign', ''))) {
            return json($this->getReturn(-1, '签名校验不通过'));
        }
        $order = Db::name('pay_order')->where('order_id', $orderId)->find();
        if (!$order) {
            return json($this->getReturn(-1, '云端订单编号不存在'));
        }
        if ((int) $order['state'] !== 0) {
            return json($this->getReturn(-1, '订单状态不允许关闭'));
        }

        Db::transaction(function () use ($order): void {
            Db::name('pay_order')->where('id', $order['id'])->update(['state' => -1, 'close_date' => time()]);
            Db::name('tmp_price')->where('oid', $order['order_id'])->delete();
        });
        return json($this->getReturn());
    }

    public function getState()
    {
        $t = (string) input('t', '');
        if (!$this->validSignature(md5($t . $this->setting('key')), (string) input('sign', '')) || !$this->validTimestamp($t)) {
            return json($this->getReturn(-1, '签名或客户端时间校验不通过'));
        }
        return json($this->getReturn(1, '成功', [
            'lastheart' => $this->setting('lastheart'),
            'lastpay' => $this->setting('lastpay'),
            'jkstate' => $this->setting('jkstate'),
        ]));
    }

    public function appHeart()
    {
        $this->closeEndOrder(true);
        $t = (string) input('t', '');
        if (!$this->validSignature(md5($t . $this->setting('key')), (string) input('sign', '')) || !$this->validTimestamp($t)) {
            return json($this->getReturn(-1, '签名或客户端时间校验不通过'));
        }

        Db::name('setting')->where('vkey', 'lastheart')->update(['vvalue' => time()]);
        Db::name('setting')->where('vkey', 'jkstate')->update(['vvalue' => 1]);
        return json($this->getReturn());
    }

    public function appPush()
    {
        $this->closeEndOrder(true);
        $t = (string) input('t', '');
        $typeRaw = (string) input('type', '');
        $priceRaw = (string) input('price', '');
        $signature = (string) input('sign', '');
        if (!$this->validSignature(md5($typeRaw . $priceRaw . $t . $this->setting('key')), $signature) || !$this->validTimestamp($t)) {
            return json($this->getReturn(-1, '签名或客户端时间校验不通过'));
        }
        $type = (int) $typeRaw;
        if (!in_array($type, [1, 2], true) || !preg_match('/^\d+(?:\.\d{1,2})?$/', $priceRaw)) {
            return json($this->getReturn(-1, '支付数据格式错误'));
        }

        $eventHash = hash('sha256', $typeRaw . '|' . $priceRaw . '|' . $t . '|' . strtolower($signature));
        if (Db::execute('INSERT IGNORE INTO push_event (event_hash, created_at) VALUES (?, ?)', [$eventHash, time()]) === 0) {
            return json($this->getReturn(1, '重复通知已忽略'));
        }
        Db::name('setting')->where('vkey', 'lastpay')->update(['vvalue' => time()]);

        $order = Db::name('pay_order')->where('really_price', number_format((float) $priceRaw, 2, '.', ''))->where('state', 0)->where('type', $type)->find();
        if (!$order) {
            $unmatchedId = 'UNMATCHED-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
            Db::name('pay_order')->insert([
                'close_date' => time(), 'create_date' => time(), 'is_auto' => 0, 'notify_url' => '',
                'notify_attempts' => 0, 'next_notify_date' => 0, 'last_notify_error' => '',
                'order_id' => $unmatchedId, 'param' => '无订单转账', 'pay_date' => time(),
                'pay_id' => $unmatchedId, 'pay_url' => '', 'price' => $priceRaw,
                'really_price' => $priceRaw, 'return_url' => '', 'state' => 1, 'type' => $type,
            ]);
            return json($this->getReturn());
        }

        Db::transaction(function () use ($order): void {
            Db::name('tmp_price')->where('oid', $order['order_id'])->delete();
            Db::name('pay_order')->where('id', $order['id'])->where('state', 0)->update([
                'state' => 1, 'pay_date' => time(), 'close_date' => time(),
            ]);
        });
        $order['state'] = 1;
        $result = $this->sendOrderNotification($order);
        if ($result !== 'success') {
            $this->markNotificationFailure($order, $result);
            return json($this->getReturn(1, '支付已记录，回调将在后台重试'));
        }
        return json($this->getReturn());
    }

    public function closeEndOrder(bool $internal = false)
    {
        $lastHeart = (int) $this->setting('lastheart');
        if (time() - $lastHeart > 70) {
            Db::name('setting')->where('vkey', 'jkstate')->update(['vvalue' => 0]);
        }

        $closeMinutes = max(1, (int) $this->setting('close'));
        $expiredOrders = Db::name('pay_order')->where('create_date', '<=', time() - 60 * $closeMinutes)->where('state', 0)->column('order_id');
        if ($expiredOrders !== []) {
            Db::transaction(function () use ($expiredOrders): void {
                Db::name('pay_order')->whereIn('order_id', $expiredOrders)->where('state', 0)->update(['state' => -1, 'close_date' => time()]);
                Db::name('tmp_price')->whereIn('oid', $expiredOrders)->delete();
            });
        }
        Db::execute('DELETE t FROM tmp_price t LEFT JOIN pay_order o ON o.order_id = t.oid WHERE o.id IS NULL');
        Db::name('push_event')->where('created_at', '<', time() - 86400)->delete();
        $this->retryFailedNotifications();

        if ($internal) {
            return null;
        }
        return json($this->getReturn(1, $expiredOrders === [] ? '没有等待清理的订单' : '成功清理' . count($expiredOrders) . '条订单'));
    }

    private function retryFailedNotifications(): void
    {
        $orders = Db::name('pay_order')->where('state', 2)->where('notify_attempts', '<', 10)
            ->where('next_notify_date', '<=', time())->limit(5)->select()->toArray();
        foreach ($orders as $order) {
            $result = $this->sendOrderNotification($order);
            if ($result === 'success') {
                Db::name('pay_order')->where('id', $order['id'])->update(['state' => 1, 'last_notify_error' => '', 'next_notify_date' => 0]);
            } else {
                $this->markNotificationFailure($order, $result);
            }
        }
    }

    private function markNotificationFailure(array $order, string $error): void
    {
        $attempts = (int) ($order['notify_attempts'] ?? 0) + 1;
        $delay = min(3600, 30 * (2 ** min($attempts - 1, 7)));
        Db::name('pay_order')->where('id', $order['id'])->update([
            'state' => 2,
            'notify_attempts' => $attempts,
            'next_notify_date' => time() + $delay,
            'last_notify_error' => mb_substr($error, 0, 250),
        ]);
    }

    private function sendOrderNotification(array $order): string
    {
        if (empty($order['notify_url'])) {
            return 'error: callback url is empty';
        }
        return SafeHttpClient::get($this->appendOrderQuery((string) $order['notify_url'], $order));
    }

    private function appendOrderQuery(string $url, array $order): string
    {
        if ($url === '') {
            return '';
        }
        $price = number_format((float) $order['price'], 2, '.', '');
        $reallyPrice = number_format((float) $order['really_price'], 2, '.', '');
        $signature = md5($order['pay_id'] . $order['param'] . $order['type'] . $price . $reallyPrice . $this->setting('key'));
        $query = http_build_query([
            'payId' => $order['pay_id'], 'param' => $order['param'], 'type' => $order['type'],
            'price' => $price, 'reallyPrice' => $reallyPrice, 'sign' => $signature,
        ], '', '&', PHP_QUERY_RFC3986);
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }

    private function orderUrls(): array
    {
        $notify = $this->setting('notifyUrl');
        $return = $this->setting('returnUrl');
        if (filter_var(env('VMQ_ALLOW_ORDER_CALLBACK_OVERRIDE', false), FILTER_VALIDATE_BOOL)) {
            $notify = (string) input('notifyUrl', $notify);
            $return = (string) input('returnUrl', $return);
        }
        return [trim($notify), trim($return)];
    }

    private function setting(string $key): string
    {
        $row = Db::name('setting')->where('vkey', $key)->find();
        return (string) ($row['vvalue'] ?? '');
    }

    private function validSignature(string $expected, string $actual): bool
    {
        return strlen($actual) === 32 && hash_equals($expected, strtolower($actual));
    }

    private function validTimestamp(string $timestamp): bool
    {
        if (!ctype_digit($timestamp)) {
            return false;
        }
        return abs((int) floor(microtime(true) * 1000) - (int) $timestamp) <= self::APP_TIMESTAMP_WINDOW_MS;
    }

    private function verifyPassword(string $password, string $stored): bool
    {
        return password_get_info($stored)['algo'] ? password_verify($password, $stored) : hash_equals($stored, $password);
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true);
    }
}
