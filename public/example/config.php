<?php

$mainConfig = require __DIR__ . '/../../config/database.php';
$db_config = [
    'hostname' => $mainConfig['hostname'],
    'database' => $mainConfig['database'],
    'username' => $mainConfig['username'],
    'password' => $mainConfig['password'],
    'hostport' => $mainConfig['hostport'],
    'charset'  => $mainConfig['charset']
];

function getVmqKey()
{
    global $db_config;

    try {
        $dsn = "mysql:host={$db_config['hostname']};port={$db_config['hostport']};dbname={$db_config['database']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT vvalue FROM setting WHERE vkey = 'key'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty($result['vvalue'])) {
            return $result['vvalue'];
        }

        return '';
    } catch (Exception $e) {
        return '';
    }
}

$key = getVmqKey();
