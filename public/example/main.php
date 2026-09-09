<?php
require_once __DIR__ . '/config.php';

$payId = (string) ($_GET['payId'] ?? '');
$param = (string) ($_GET['param'] ?? '');
$type = (string) ($_GET['type'] ?? '');
$price = (string) ($_GET['price'] ?? '');
if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $payId) || !in_array($type, ['1', '2'], true) || !preg_match('/^\d+(?:\.\d{1,2})?$/', $price)) {
    http_response_code(400);
    exit('参数错误');
}

$sign = md5($payId . $param . $type . (float) $price . $key);
$query = http_build_query([
    'payId' => $payId,
    'param' => $param,
    'type' => $type,
    'price' => $price,
    'sign' => $sign,
    'isHtml' => 1,
], '', '&', PHP_QUERY_RFC3986);

header('Location: ../createOrder?' . $query, true, 302);
