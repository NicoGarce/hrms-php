<?php
// Database connection (MySQL via PDO)
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'hrms_php';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
// Allow override via config file
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    $cfg = include $configFile;
    if (isset($cfg['host'])) $DB_HOST = $cfg['host'];
    if (isset($cfg['port'])) $DB_PORT = $cfg['port'];
    if (isset($cfg['dbname'])) $DB_NAME = $cfg['dbname'];
    if (isset($cfg['user'])) $DB_USER = $cfg['user'];
    if (isset($cfg['pass'])) $DB_PASS = $cfg['pass'];
}
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // If DB not exists, try to create it, then reconnect
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        try {
            $tmp = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $tmp->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Exception $ex) {
            die("Database connection failed: " . htmlspecialchars($ex->getMessage()) . "<br>Please import database.sql via phpMyAdmin.");
        }
    } else {
        die("Database connection failed: " . htmlspecialchars($e->getMessage()));
    }
}

function db(): PDO {
    global $pdo;
    return $pdo;
}
function uuid(): string {
    // Generate v4 UUID
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
