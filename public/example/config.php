<?php

$db_config = [
    'hostname' => getenv('VMQ_DB_HOST') ?: 'db',
    'database' => getenv('VMQ_DB_NAME') ?: 'vmq',
    'username' => getenv('VMQ_DB_USER') ?: 'vmq',
    'password' => getenv('VMQ_DB_PASSWORD') ?: '',
    'hostport' => getenv('VMQ_DB_PORT') ?: '3306',
    'charset'  => 'utf8mb4',
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
    } catch (Throwable $e) {
        return '';
    }
}

$key = getVmqKey();
