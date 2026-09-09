<?php
ini_set("error_reporting", "E_ALL & ~E_NOTICE");

require_once __DIR__ . '/config.php';

$payId       = $_GET['payId']       ?? '';
$param       = $_GET['param']       ?? '';
$type        = $_GET['type']        ?? '';
$price       = $_GET['price']       ?? '';
$reallyPrice = $_GET['reallyPrice'] ?? '';
$sign        = $_GET['sign']        ?? '';

$_sign = md5($payId . $param . $type . $price . $reallyPrice . $key);
if (strlen($sign) !== 32 || !hash_equals($_sign, strtolower($sign))) {
    echo "error_sign";
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付成功</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", "Microsoft YaHei", sans-serif;
        }

        body {
            background-color: #f0f0f0;
            text-align: center;
            padding: 40px 20px;
        }

        .success-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 12px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin: 0 auto;
            animation: cardUp 0.7s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        @keyframes cardUp {
            0% {
                opacity: 0;
                transform: translateY(40px) scale(0.96);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .icon-circle {
            width: 88px;
            height: 88px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: circlePop 0.6s ease-out;
        }

        @keyframes circlePop {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            60% {
                transform: scale(1.15);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .icon-circle svg {
            width: 44px;
            height: 44px;
            animation: iconBounce 0.5s ease-out 0.3s both;
        }

        @keyframes iconBounce {
            0% {
                transform: scale(0) rotate(-10deg);
                opacity: 0;
            }

            70% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-title {
            font-size: 24px;
            color: #333;
            font-weight: 600;
            margin-bottom: 30px;
            animation: fadeShow 0.6s ease 0.4s both;
        }

        @keyframes fadeShow {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .order-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px 20px;
            text-align: left;
            margin-bottom: 30px;
            animation: fadeShow 0.6s ease 0.5s both;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
            margin-bottom: 14px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-row .val {
            color: #333;
            font-weight: 500;
        }

        .info-row .price {
            color: #07c160;
            font-size: 15px;
            font-weight: 600;
        }

        .info-row .price.zfb {
            color: #1677FF;
        }

        .back-btn {
            display: inline-block;
            padding: 12px 36px;
            background: #07c160;
            color: #fff;
            border-radius: 30px;
            text-decoration: none;
            font-size: 15px;
            transition: all 0.25s ease;
            animation: fadeShow 0.6s ease 0.7s both;
        }

        .back-btn:hover {
            opacity: 0.9;
        }

        .back-btn.zfb {
            background: #1677FF;
        }
    </style>
</head>

<body>
    <div class="success-card">
        <div class="icon-circle">
            <?php if ($type == 1): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                    <path d="M964.16 294.4c-3.328-5.568-5.888-5.76-11.168-2.528-17.44 10.56-35.168 20.64-52.864 30.752-45.024 25.76-90.144 51.424-135.2 77.184-53.344 30.464-106.688 60.896-159.936 91.488-75.328 43.264-150.56 86.656-225.856 129.984-21.056 12.096-41.184 5.6-51.232-16.48-8.448-18.432-16.992-36.832-25.44-55.264-21.92-48.032-43.84-96.064-65.696-144.128-4.032-8.864-2.528-15.264 4.16-20.8 6.304-5.28 13.824-5.248 21.568 0.256 35.136 24.864 70.08 49.92 105.376 74.56 15.84 11.072 33.12 12.64 51.008 4.8 45.824-20.16 91.648-40.224 137.376-60.48 64.928-28.8 129.728-57.76 194.624-86.528a30516.544 30516.544 0 0 1 165.056-72.8c7.136-3.104 7.616-5.44 2.56-11.104-40.96-45.664-89.376-81.248-144.32-108.864a568.64 568.64 0 0 0-159.488-52.064c-33.728-5.856-67.84-9.536-99.712-7.68-45.92-1.28-88.576 3.968-130.976 13.088a560.096 560.096 0 0 0-98.976 30.784c-85.76 35.904-157.696 89.216-211.008 165.76C30.816 336.32 8.352 405.12 7.776 480.48a340.96 340.96 0 0 0 15.264 102.656c20.16 66.24 56.832 122.016 106.176 170.24 16.832 16.416 35.136 31.072 53.792 45.28 11.584 8.8 15.552 20.704 12.224 34.048-6.752 27.136-14.72 54.016-22.144 80.992l-3.648 13.696a15.776 15.776 0 0 0 6.304 17.792c6.496 4.544 13.76 3.424 20.576-0.48 36.352-20.8 72.704-41.6 109.12-62.272 10.624-6.048 21.888-10.528 34.432-8.032 9.312 1.824 18.496 4.416 27.68 6.912 39.36 10.656 79.616 15.808 120.288 17.216 41.248 1.408 82.304-0.64 123.2-7.424 34.112-5.632 67.584-13.504 99.872-25.216 58.976-21.344 113.568-50.72 161.6-91.424 66.944-56.64 113.856-125.6 134.88-211.104a349.12 349.12 0 0 0 5.184-139.2c-7.36-46.304-24.224-89.44-48.416-129.792" fill="#07C160" />
                </svg>
            <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                    <path d="M588.8 672s-76.8 64-102.4 76.8c-25.6 12.8-44.8 25.6-70.4 32-19.2 6.4-38.4 12.8-51.2 12.8s-25.6 0-38.4 6.4H307.2c-25.6 0-51.2 0-76.8-6.4l-57.6-38.4c-19.2-12.8-32-25.6-38.4-44.8-12.8-19.2-12.8-38.4-12.8-64 0-19.2 6.4-38.4 19.2-57.6 12.8-19.2 25.6-32 44.8-44.8s38.4-19.2 57.6-25.6c12.8-6.4 38.4-6.4 57.6-6.4 19.2 0 44.8 0 64 6.4 19.2 6.4 38.4 6.4 51.2 12.8 19.2 6.4 32 12.8 51.2 19.2 19.2 6.4 32 12.8 44.8 19.2 6.4 6.4 12.8 6.4 19.2 6.4 6.4 0 12.8 6.4 19.2 6.4 19.2-12.8 25.6-32 38.4-51.2 6.4-19.2 19.2-32 25.6-44.8 6.4-12.8 6.4-25.6 12.8-38.4 6.4-6.4 6.4-19.2 6.4-19.2H320v-32h147.2V313.6H262.4v-32h204.8v-64c0-6.4 0-6.4 6.4-12.8 6.4 0 12.8-6.4 19.2-6.4H576v83.2h211.2v32H569.6v76.8h166.4c-6.4 19.2-6.4 44.8-19.2 70.4-6.4 19.2-19.2 44.8-32 70.4-12.8 25.6-32 51.2-51.2 83.2 0 0 166.4 76.8 339.2 108.8 32-64 44.8-134.4 44.8-211.2 0-281.6-230.4-512-512-512C230.4 0 0 230.4 0 512s230.4 512 512 512c172.8 0 332.8-89.6 422.4-224-96-19.2-192-57.6-345.6-128z m-403.2-25.6c0 89.6 96 96 115.2 96 51.2 0 89.6-19.2 121.6-38.4 32-12.8 83.2-64 83.2-64l6.4-6.4c-12.8-6.4-25.6-19.2-38.4-25.6-12.8-6.4-83.2-38.4-140.8-44.8-115.2-12.8-147.2 57.6-147.2 83.2z" fill="#1677FF" />
                </svg>
            <?php endif; ?>
        </div>

        <h1 class="success-title">支付成功</h1>

        <div class="order-info">
            <div class="info-row">
                <span>商户订单号</span>
                <span class="val"><?php echo $payId; ?></span>
            </div>
            <div class="info-row">
                <span>自定义参数</span>
                <span class="val"><?php echo $param; ?></span>
            </div>
            <div class="info-row">
                <span>支付方式</span>
                <span class="val">
                    <?php echo $type == 1 ? '微信支付' : '支付宝支付'; ?>
                </span>
            </div>
            <div class="info-row">
                <span>订单金额</span>
                <span class="val">¥<?php echo $price; ?></span>
            </div>
            <div class="info-row">
                <span>实际支付</span>
                <span class="price <?php echo $type == 2 ? 'zfb' : ''; ?>">¥<?php echo $reallyPrice; ?></span>
            </div>
        </div>

        <a href="./" class="back-btn <?php echo $type == 2 ? 'zfb' : ''; ?>">返回上一页</a>
    </div>
</body>

</html>
