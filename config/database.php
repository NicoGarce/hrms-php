<?php
// Database connection (MySQL via PDO) with auto-install for live deployment.
// On first load: creates DB if missing (when permitted) + imports database.sql
// (tables + seed data) automatically so opening the app sets everything up.
$DB_HOST = getenv('DB_HOST') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '';
$DB_NAME = getenv('DB_NAME') ?: '';
$DB_USER = getenv('DB_USER') ?: '';
$DB_PASS = getenv('DB_PASS');
// Fall back to config file only for values NOT provided via env (env wins on live)
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    $cfg = include $configFile;
    if ($DB_HOST === '' && isset($cfg['host'])) $DB_HOST = $cfg['host'];
    if ($DB_PORT === '' && isset($cfg['port'])) $DB_PORT = $cfg['port'];
    if ($DB_NAME === '' && isset($cfg['dbname'])) $DB_NAME = $cfg['dbname'];
    if ($DB_USER === '' && isset($cfg['user'])) $DB_USER = $cfg['user'];
    // 'pass' may legitimately be empty string, so check existence not emptiness
    if (($DB_PASS === '' || $DB_PASS === false || $DB_PASS === null) && array_key_exists('pass', $cfg)) $DB_PASS = $cfg['pass'];
}
if ($DB_HOST === '') $DB_HOST = '127.0.0.1';
if ($DB_PORT === '') $DB_PORT = '3306';
if ($DB_NAME === '') $DB_NAME = 'hrms_php';
if ($DB_USER === '') $DB_USER = 'root';
if ($DB_PASS === false || $DB_PASS === null) $DB_PASS = '';

function db_connect(string $host, string $port, ?string $dbname, string $user, $pass): PDO {
    $dsn = $dbname !== null
        ? "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4"
        : "mysql:host={$host};port={$port};charset=utf8mb4";
    return new PDO($dsn, $user, (string)$pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/**
 * Import database.sql statement-by-statement.
 * Safe to run multiple times: schema uses IF NOT EXISTS,
 * seeds use ON DUPLICATE KEY UPDATE / INSERT IGNORE.
 */
function db_auto_install(PDO $pdo, string $dbName): void {
    $sqlFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database.sql';
    if (!file_exists($sqlFile)) {
        die("Database tables are missing and <b>database.sql</b> was not found. Please upload database.sql next to index.php.");
    }
    @set_time_limit(0);
    $raw = file_get_contents($sqlFile);
    // Strip CREATE DATABASE / USE lines — DB is already selected.
    // (On shared hosting the DB user usually can't CREATE DATABASE anyway;
    // the host pre-creates it and we just need the tables inside it.)
    $raw = preg_replace('/^\s*CREATE DATABASE.*?;[\r\n]*/mi', '', $raw);
    $raw = preg_replace('/^\s*USE\s+`?[\w$]+`?\s*;[\r\n]*/mi', '', $raw);
    // Split into individual statements on semicolon + line break.
    // (database.sql has no stored procedures/triggers, so no DELIMITER handling needed.)
    $statements = preg_split('/;[\r\n]+/', $raw);
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        // Skip pure comment blocks
        $lines = array_filter(
            array_map('trim', explode("\n", $stmt)),
            fn($l) => $l !== '' && strpos($l, '--') !== 0 && strpos($l, '#') !== 0
        );
        if (empty($lines)) continue;
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // Ignore "already exists / duplicate" noise from re-runs, fail on real errors
            $msg = $e->getMessage();
            if (stripos($msg, 'already exists') !== false) continue;
            if (stripos($msg, 'Duplicate entry') !== false) continue;
            die("Auto-install failed while creating tables: " . htmlspecialchars($msg) .
                "<br>Try importing <b>database.sql</b> manually via phpMyAdmin into <b>" . htmlspecialchars($dbName) . "</b>.");
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
}

/** Returns true if core schema is present, false if we need to import database.sql */
function db_schema_present(PDO $pdo): bool {
    try {
        // `users` is created early in database.sql; if it exists, schema was installed
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : false;
        if (!$row) return false;
        // Also treat an empty `roles` table as not-yet-seeded
        $count = (int)$pdo->query("SELECT COUNT(*) AS c FROM `roles`")->fetch()['c'];
        return $count > 0;
    } catch (Exception $e) {
        return false;
    }
}

try {
    try {
        $pdo = db_connect($DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS);
    } catch (PDOException $e) {
        // DB itself missing → try to create it (works on XAMPP/VPS; on shared
        // hosting this step fails gracefully and we show instructions instead)
        if (strpos($e->getMessage(), 'Unknown database') !== false) {
            try {
                $tmp = db_connect($DB_HOST, $DB_PORT, null, $DB_USER, $DB_PASS);
                $tmp->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = db_connect($DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS);
            } catch (Exception $ex) {
                die("Database <b>" . htmlspecialchars($DB_NAME) . "</b> does not exist and could not be auto-created (" .
                    htmlspecialchars($ex->getMessage()) . ").<br>" .
                    "On live/shared hosting: create the database in cPanel first, set DB_HOST/DB_NAME/DB_USER/DB_PASS " .
                    "env vars (or edit config/config.php), then reload this page.");
            }
        } else {
            throw $e;
        }
    }
    // First-open auto-install: empty DB (or fresh hosting DB) → import tables + seed
    if (!db_schema_present($pdo)) {
        db_auto_install($pdo, $DB_NAME);
    }
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
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
