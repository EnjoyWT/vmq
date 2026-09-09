<?php

namespace app\admin\controller;

use think\App;
use think\facade\Db;
use think\facade\Session;
use app\service\QrcodeServer;
use app\service\SafeHttpClient;
use Composer\InstalledVersions;
use Zxing\QrReader;

class Index
{
    public function index()
    {
        return 'by:vone';
    }

    public function getReturn($code = 1, $msg = "成功", $data = null)
    {
        return array("code" => $code, "msg" => $msg, "data" => $data);
    }

    public function getMain()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        $today = strtotime(date("Y-m-d"), time());

        $todayOrder = Db::name("pay_order")
            ->where("create_date", ">=", $today)
            ->where("create_date", "<=", $today + 86400)
            ->count();


        $todaySuccessOrder = Db::name("pay_order")
            ->where("state", ">=", 1)
            ->where("create_date", ">=", $today)
            ->where("create_date", "<=", $today + 86400)
            ->count();



        $todayCloseOrder = Db::name("pay_order")
            ->where("state", -1)
            ->where("create_date", ">=", $today)
            ->where("create_date", "<=", $today + 86400)
            ->count();

        $todayMoney = Db::name("pay_order")
            ->where("state", ">=", 1)
            ->where("create_date", ">=", $today)
            ->where("create_date", "<=", $today + 86400)
            ->sum("price");


        $countOrder = Db::name("pay_order")
            ->count();
        $countMoney = Db::name("pay_order")
            ->where("state", ">=", 1)
            ->sum("price");

        $v = Db::query("SELECT VERSION();");
        $v = $v[0]['VERSION()'];

        if (function_exists("gd_info")) {
            $gd_info = @gd_info();
            $gd = $gd_info["GD Version"];
        } else {
            $gd = '<font color="red">GD库未开启！</font>';
        }

        return json($this->getReturn(1, "成功", array(
            "todayOrder" => $todayOrder,
            "todaySuccessOrder" => $todaySuccessOrder,
            "todayCloseOrder" => $todayCloseOrder,
            "todayMoney" => round($todayMoney, 2),
            "countOrder" => $countOrder,
            "countMoney" => round($countMoney),

            "PHP_VERSION" => PHP_VERSION,
            "PHP_OS" => PHP_OS,
            "SERVER" => $_SERVER['SERVER_SOFTWARE'],
            "MySql" => $v,
            "Thinkphp" => InstalledVersions::getPrettyVersion('topthink/framework') ?: ("v" . App::VERSION),
            "ver" => "v" . config("app.ver"),
            "RunTime" => $this->sys_uptime(),
            "startTime" => $this->get_start_time(),
            "gd" => $gd,
        )));
    }
    private function get_start_time()
    {
        $startTime = Db::name("setting")->where("vkey", "startTime")->find();
        $currentTime = time();
        if (!$startTime || empty($startTime["vvalue"]) || !is_numeric($startTime["vvalue"]) || $startTime["vvalue"] < 1000000000) {
            if (!$startTime) {
                Db::name("setting")->insert(["vkey" => "startTime", "vvalue" => $currentTime]);
            } else {
                Db::name("setting")->where("vkey", "startTime")->update(["vvalue" => $currentTime]);
            }
            return $currentTime;
        }
        return intval($startTime["vvalue"]);
    }

    private function sys_uptime()
    {
        $startTime = $this->get_start_time();
        $totalSeconds = time() - $startTime;
        if ($totalSeconds < 0) {
            $totalSeconds = 0;
        }

        $days = floor($totalSeconds / 86400);
        $hours = floor(($totalSeconds % 86400) / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        $output = $days . "天" . $hours . "小时" . $minutes . "分钟" . $seconds . "秒";

        return $output;
    }

    public function getSettings()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        $user = Db::name("setting")->where("vkey", "user")->find();
        $notifyUrl = Db::name("setting")->where("vkey", "notifyUrl")->find();
        $returnUrl = Db::name("setting")->where("vkey", "returnUrl")->find();
        $key = Db::name("setting")->where("vkey", "key")->find();
        $lastheart = Db::name("setting")->where("vkey", "lastheart")->find();
        $lastpay = Db::name("setting")->where("vkey", "lastpay")->find();
        $jkstate = Db::name("setting")->where("vkey", "jkstate")->find();
        $close = Db::name("setting")->where("vkey", "close")->find();
        $payQf = Db::name("setting")->where("vkey", "payQf")->find();
        $wxpay = Db::name("setting")->where("vkey", "wxpay")->find();
        $zfbpay = Db::name("setting")->where("vkey", "zfbpay")->find();
        if ($key['vvalue'] == "") {
            $key['vvalue'] = bin2hex(random_bytes(24));
            Db::name("setting")->where("vkey", "key")->update(array(
                "vvalue" => $key['vvalue']
            ));
        }

        return json($this->getReturn(1, "成功", array(
            "user" => $user['vvalue'],
            "pass" => "******",
            "notifyUrl" => $notifyUrl['vvalue'],
            "returnUrl" => $returnUrl['vvalue'],
            "key" => $key['vvalue'],
            "lastheart" => $lastheart['vvalue'],
            "lastpay" => $lastpay['vvalue'],
            "jkstate" => $jkstate['vvalue'],
            "close" => $close['vvalue'],
            "payQf" => $payQf['vvalue'],
            "wxpay" => $wxpay['vvalue'],
            "zfbpay" => $zfbpay['vvalue'],

        )));
    }
    public function saveSetting()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $user = trim((string) input("user", ""));
        $newPass = (string) input("pass", "******");
        $notifyUrl = trim((string) input("notifyUrl", ""));
        $returnUrl = trim((string) input("returnUrl", ""));
        $key = trim((string) input("key", ""));
        if ($user === "" || strlen($user) > 64) {
            return json($this->getReturn(-1, "账号不能为空且不能超过64位"));
        }
        if ($newPass !== "******" && strlen($newPass) < 10) {
            return json($this->getReturn(-1, "后台密码至少10位"));
        }
        if (strlen($key) < 16) {
            return json($this->getReturn(-1, "通讯密钥至少16位"));
        }
        if ($notifyUrl !== "" && !SafeHttpClient::isPublicUrl($notifyUrl)) {
            return json($this->getReturn(-1, "回调地址必须是可公网解析的 HTTP/HTTPS 地址"));
        }
        if ($returnUrl !== "" && !$this->isHttpUrl($returnUrl)) {
            return json($this->getReturn(-1, "同步跳转地址格式错误"));
        }

        Db::name("setting")->where("vkey", "user")->update(["vvalue" => $user]);
        if ($newPass !== "******") {
            Db::name("setting")->where("vkey", "pass")->update(["vvalue" => password_hash($newPass, PASSWORD_DEFAULT)]);
        }
        Db::name("setting")->where("vkey", "notifyUrl")->update(["vvalue" => $notifyUrl]);
        Db::name("setting")->where("vkey", "returnUrl")->update(["vvalue" => $returnUrl]);
        Db::name("setting")->where("vkey", "key")->update(["vvalue" => $key]);
        Db::name("setting")->where("vkey", "close")->update(["vvalue" => max(1, min(60, intval(input("close"))))]);
        Db::name("setting")->where("vkey", "payQf")->update(["vvalue" => intval(input("payQf")) === 2 ? 2 : 1]);
        Db::name("setting")->where("vkey", "wxpay")->update(["vvalue" => trim((string) input("wxpay", ""))]);
        Db::name("setting")->where("vkey", "zfbpay")->update(["vvalue" => trim((string) input("zfbpay", ""))]);

        return json($this->getReturn());
    }


    public function addPayQrcode()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        $type = intval(input("type"));
        $pay_url = filter_var(input("pay_url"), FILTER_SANITIZE_URL);
        $price = floatval(input("price"));

        if ($type != 1 && $type != 2) {
            return json($this->getReturn(-1, "支付类型错误"));
        }
        if ($price <= 0) {
            return json($this->getReturn(-1, "金额必须大于0"));
        }
        if (empty($pay_url)) {
            return json($this->getReturn(-1, "支付链接不能为空"));
        }

        $db = Db::name("pay_qrcode")->insert(array(
            "type" => $type,
            "pay_url" => $pay_url,
            "price" => $price,
        ));
        return json($this->getReturn());
    }

    public function decodeQrcode()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        $encoded = (string) input('base64', '');
        $binary = base64_decode($encoded, true);
        if ($binary === false || strlen($binary) === 0 || strlen($binary) > 8 * 1024 * 1024) {
            return json($this->getReturn(-1, '图片为空、格式错误或超过8MB'));
        }
        if (@getimagesizefromstring($binary) === false) {
            return json($this->getReturn(-1, '上传内容不是有效图片'));
        }

        try {
            $text = (new QrReader($binary, QrReader::SOURCE_TYPE_BLOB, false))->text();
            if (!$text) {
                return json($this->getReturn(-1, '未识别到二维码'));
            }
            return json($this->getReturn(1, '识别成功', $text));
        } catch (\Throwable $e) {
            return json($this->getReturn(-1, '二维码识别失败'));
        }
    }

    public function getPayQrcodes()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        $page = input("page");
        $size = input("limit");

        $obj = Db::table('pay_qrcode')->page($page, $size);

        $obj = $obj->where("type", input("type"));

        $array = $obj->order("id", "desc")->select();

        //echo $obj->getLastSql();
        return json(array(
            "code" => 0,
            "msg" => "获取成功",
            "data" => $array,
            "count" => $obj->count()
        ));
    }
    public function delPayQrcode()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        Db::name("pay_qrcode")->where("id", input("id"))->delete();
        return json($this->getReturn());
    }

    public function getOrders()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        $page = input("page");
        $size = input("limit");

        $obj = Db::table('pay_order')->page($page, $size);
        if (input("type")) {
            $obj = $obj->where("type", input("type"));
        }
        if (input("state")) {
            $obj = $obj->where("state", input("state"));
        }


        $array = $obj->order("id", "desc")->select();

        //echo $obj->getLastSql();
        return json(array(
            "code" => 0,
            "msg" => "获取成功",
            "data" => $array,
            "count" => $obj->count()
        ));
    }
    public function delOrder()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        $res = Db::name("pay_order")->where("id", input("id"))->find();

        Db::name("pay_order")->where("id", input("id"))->delete();
        if ($res['state'] == 0) {
            Db::name("tmp_price")
                ->where("oid", $res['order_id'])
                ->delete();
        }

        return json($this->getReturn());
    }

    public function setBd()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        $res = Db::name("pay_order")->where("id", input("id"))->find();

        if ($res) {

            $url = $res['notify_url'];

            $res2 = Db::name("setting")->where("vkey", "key")->find();
            $key = $res2['vvalue'];

            $price = number_format((float) $res['price'], 2, '.', '');
            $reallyPrice = number_format((float) $res['really_price'], 2, '.', '');
            $sign = md5($res['pay_id'] . $res['param'] . $res['type'] . $price . $reallyPrice . $key);
            $query = http_build_query([
                'payId' => $res['pay_id'],
                'param' => $res['param'],
                'type' => $res['type'],
                'price' => $price,
                'reallyPrice' => $reallyPrice,
                'sign' => $sign,
            ], '', '&', PHP_QUERY_RFC3986);
            $url .= str_contains($url, '?') ? '&' . $query : '?' . $query;

            $re = $this->getCurl($url);

            if ($re == "success") {
                if ($res['state'] == 0) {
                    Db::name("tmp_price")
                        ->where("oid", $res['order_id'])
                        ->delete();
                }

                Db::name("pay_order")->where("id", $res['id'])->update(array("state" => 1));

                return json($this->getReturn());
            } else {
                return json($this->getReturn(-2, "补单失败", $re));
            }
        } else {
            return json($this->getReturn(-1, "订单不存在"));
        }
    }

    public function delGqOrder()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }
        Db::name("pay_order")->where("state", "-1")->delete();
        return json($this->getReturn());
    }
    public function delLastOrder()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        Db::name("pay_order")->where("create_date", "<", time() - 604800)->delete();
        return json($this->getReturn());
    }

    public function delAllOrder()
    {
        if (!Session::has("admin")) {
            return json($this->getReturn(-1, "没有登录"));
        }

        Db::name("pay_order")->whereRaw('1=1')->delete();
        Db::name("tmp_price")->whereRaw('1=1')->delete();
        return json($this->getReturn());
    }

    public function enQrcode($url)
    {

        $qr_code = new QrcodeServer(['generate' => "display", "size" => 200]);
        $content = $qr_code->createServer($url);

        return response($content, 200, ['Content-Length' => strlen($content)])->contentType('image/png');
    }


    //获取客户IP
    public function ip()
    {

        return $_SERVER['REMOTE_ADDR'];
    }
    private function getCurl(string $url): string
    {
        return SafeHttpClient::get($url);
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true);
    }
}
