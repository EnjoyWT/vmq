<?php
ini_set("error_reporting", "E_ALL & ~E_NOTICE");

require_once __DIR__ . '/config.php';

$payId      = $_GET['payId']      ?? '';
$param      = $_GET['param']      ?? '';
$type       = $_GET['type']       ?? '';
$price      = $_GET['price']      ?? '';
$reallyPrice = $_GET['reallyPrice'] ?? '';
$sign       = $_GET['sign']       ?? '';

$_sign = md5($payId . $param . $type . $price . $reallyPrice . $key);
if (strlen($sign) !== 32 || !hash_equals($_sign, strtolower($sign))) {
    echo "error_sign";
    exit();
}

echo "success";
