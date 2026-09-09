# V免签（Vmq）

本地支付通知与回调服务，配合 V免签 Android 监控端使用。手机监听微信/支付宝到账通知，通过局域网把结果发给本机 Vmq；Vmq 再把已验签的支付结果通知到你的 Cloudflare 业务服务。

当前运行栈：PHP 8.3、ThinkPHP 8.1、MariaDB 11.4 LTS、Apache。项目默认用 Docker Compose 部署，兼容 Apple Silicon。

## 数据流

```text
Cloudflare 业务服务 ──创建/查询订单──> Vmq（Mac:18473）
                                             ▲
                                             │ 局域网 HTTP：心跳/到账通知
                                      Android 监控端
                                             │
                                      微信 / 支付宝通知

Vmq ──HTTPS 支付回调──> Cloudflare 业务服务
```

仅有 “Vmq 回调到 CF” 时是本机主动出站，不需要开放路由器端口。若 CF 还需要主动调用本机的 `createOrder`，再为这些 API 配 Cloudflare Tunnel；不要把管理后台直接暴露到公网。

## Docker 部署

### 1. 准备配置

```bash
cp .env.example .env
```

编辑 `.env`，至少替换下面四项，且四个值不要复用：

```dotenv
VMQ_DB_PASSWORD=随机长密码
VMQ_DB_ROOT_PASSWORD=另一个随机长密码
VMQ_ADMIN_PASSWORD=后台登录密码（至少10位）
VMQ_API_KEY=APP通讯密钥（至少32位）
VMQ_CALLBACK_SECRET=与 AuthHub 相同的回调 HMAC 密钥
```

推荐生成方式：

```bash
openssl rand -hex 32
```

把 Cloudflare 接收支付结果的 HTTPS 地址写入：

```dotenv
VMQ_NOTIFY_URL=https://auth.yoloxy.com/api/billing/vmq/notify
VMQ_RETURN_URL=
```

CF 回调接口验签成功并完成幂等处理后，响应体必须原样返回 `success`。配置 `VMQ_CALLBACK_SECRET` 后，Vmq 使用 POST JSON 和 HMAC-SHA256 回调；Vmq 对失败回调采用指数退避自动重试，最多 10 次。

### 2. 启动

```bash
docker compose up -d --build
docker compose ps
```

默认监听高位端口 `18473`，MariaDB 只在 Docker 内网开放。浏览器访问：

```text
http://本机局域网IP:18473/index.html
```

查看本机 Wi-Fi 地址：

```bash
ipconfig getifaddr en0
```

如需更换端口，修改 `.env` 的 `VMQ_HTTP_PORT` 后执行：

```bash
docker compose up -d
```

### 3. 配置 Android APP

登录后台 → 系统设置，配置微信/支付宝收款码；再到“监控端设置”扫码。手工填写时，服务端格式为：

```text
本机局域网IP:18473/VMQ_API_KEY
```

例如：`192.168.11.115:18473/你的通讯密钥`。手机和 Mac 必须在同一可信局域网；路由器不要开启 AP 隔离。Mac 的 DHCP 地址如果会变化，建议在路由器给它设置固定租约。

## 常用运维命令

```bash
# 状态与健康检查
docker compose ps
curl http://127.0.0.1:18473/health

# 日志
docker compose logs -f --tail=200 web db

# 更新代码后重建
docker compose up -d --build

# 停止（保留数据）
docker compose down
```

数据库和运行时数据分别保存在 `vmq_db_data`、`vmq_runtime` 命名卷。不要用 `docker compose down -v`，除非明确要删除所有订单与设置。

## 接口

### 业务端

| 路径 | 说明 |
|---|---|
| `/createOrder` | 创建订单 |
| `/getOrder` | 查询订单详情 |
| `/checkOrder` | 查询支付状态 |
| `/closeOrder` | 关闭未支付订单 |
| `/getState` | 查询监控端状态 |

### Android 监控端

| 路径 | 说明 |
|---|---|
| `/appHeart` | 心跳；时间戳有效窗口 120 秒 |
| `/appPush` | 到账通知；带时间戳校验和重复事件去重 |

现有 APK 的 MD5 拼接签名协议保持兼容，具体参数见 `/api.html`。通讯密钥只用于这套 Vmq，不要与数据库密码或 CF 密钥复用。

默认不允许单笔订单覆盖 `notifyUrl` / `returnUrl`，以免订单接口被利用发起 SSRF。如果确实要多商户动态回调，可在充分评估后设置 `VMQ_ALLOW_ORDER_CALLBACK_OVERRIDE=true`；动态通知地址仍只允许解析到公网 IP 的 HTTP/HTTPS URL。

## 本次现代化与修复

- ThinkPHP 5.1 迁移至 ThinkPHP 8.1.4，Composer 依赖已锁定且安全审计无已知漏洞。
- PHP 7.2 / MySQL 5.7 部署方式替换为 PHP 8.3 / MariaDB 11.4 LTS 容器。
- 数据库统一为 `vmq`，金额改用 `DECIMAL`，表引擎改为 InnoDB，并增加订单唯一索引。
- 后台密码改为 `password_hash`；旧明文密码首次登录后自动升级。
- 恢复 APP 时间戳校验，增加支付推送去重，避免重放生成重复记录。
- 回调开启 TLS 证书与主机名校验；解析并固定公网目标 IP，阻止内网 SSRF 与 DNS 重绑定。
- 回调失败不再让 APP 重复上报付款，而是记录已支付状态并后台重试。
- 修复订单金额占用残留、随机订单号碰撞和加价分支重复 `+0.01` 等问题。
- Cookie 启用 HttpOnly、SameSite=Lax，并加入容器健康检查、日志轮转与持久化。

## 订单状态

| 状态 | 含义 |
|---|---|
| `0` | 待支付 |
| `1` | 已支付且回调成功 |
| `2` | 已支付，回调等待重试或最终失败 |
| `-1` | 已过期/已关闭 |

## 免责声明

本软件仅供学习研究。请遵守微信、支付宝、Cloudflare 及所在地法律法规和服务条款；支付结果在 CF 侧必须按 `payId` 做幂等处理。

Apache-2.0 · 基于 [szvone/vmqphp](https://github.com/szvone/vmqphp) 修改。
