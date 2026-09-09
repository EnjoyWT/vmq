<?php

declare(strict_types=1);

function requiredEnv(string $name, int $minLength): string
{
    $value = (string) getenv($name);
    if (strlen($value) < $minLength || str_starts_with($value, 'replace-with-')) {
        fwrite(STDERR, $name . " 未设置或长度不足\n");
        exit(1);
    }
    return $value;
}

$dbPassword = requiredEnv('VMQ_DB_PASSWORD', 16);
$adminPassword = requiredEnv('VMQ_ADMIN_PASSWORD', 10);
$apiKey = requiredEnv('VMQ_API_KEY', 32);
$host = (string) (getenv('VMQ_DB_HOST') ?: 'db');
$port = (string) (getenv('VMQ_DB_PORT') ?: '3306');
$database = (string) (getenv('VMQ_DB_NAME') ?: 'vmq');
$user = (string) (getenv('VMQ_DB_USER') ?: 'vmq');

$pdo = null;
for ($attempt = 0; $attempt < 30; $attempt++) {
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        break;
    } catch (PDOException $e) {
        if ($attempt === 29) {
            throw $e;
        }
        sleep(2);
    }
}

$pdo->exec("CREATE TABLE IF NOT EXISTS push_event (
    event_hash CHAR(64) NOT NULL PRIMARY KEY,
    created_at BIGINT UNSIGNED NOT NULL,
    KEY idx_push_event_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii");

$columns = [
    'notify_attempts' => "TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER notify_url",
    'next_notify_date' => "BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER notify_attempts",
    'last_notify_error' => "VARCHAR(255) NOT NULL DEFAULT '' AFTER next_notify_date",
    'notify_event_id' => "VARCHAR(64) NULL DEFAULT NULL AFTER last_notify_error",
];
foreach ($columns as $column => $definition) {
    $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $query->execute([$database, 'pay_order', $column]);
    if ((int) $query->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE pay_order ADD COLUMN {$column} {$definition}");
    }
}

$indexQuery = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
);
$indexQuery->execute([$database, 'pay_order', 'uk_pay_order_notify_event_id']);
if ((int) $indexQuery->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE pay_order ADD UNIQUE KEY uk_pay_order_notify_event_id (notify_event_id)');
}

$select = $pdo->prepare('SELECT vvalue FROM setting WHERE vkey = ?');
$upsert = $pdo->prepare('INSERT INTO setting (vkey, vvalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE vvalue = VALUES(vvalue)');
$select->execute(['pass']);
$storedPassword = (string) ($select->fetchColumn() ?: '');
if ($storedPassword === '' || $storedPassword === '123456') {
    $upsert->execute(['pass', password_hash($adminPassword, PASSWORD_DEFAULT)]);
}
$select->execute(['key']);
if ((string) ($select->fetchColumn() ?: '') === '') {
    $upsert->execute(['key', $apiKey]);
}
foreach (['VMQ_NOTIFY_URL' => 'notifyUrl', 'VMQ_RETURN_URL' => 'returnUrl'] as $envName => $settingName) {
    $value = trim((string) getenv($envName));
    $select->execute([$settingName]);
    if ($value !== '' && (string) ($select->fetchColumn() ?: '') === '') {
        $upsert->execute([$settingName, $value]);
    }
}
