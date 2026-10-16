<?php
// Migration runner: applies database/migrations/*.sql in order.
// Idempotent: tracks applied files in `migrations` table; preserves data.
// Usage (CLI): php database/migrations/migrate.php
// Also auto-called from config/database.php? No - run manually / on deploy.
$root = dirname(__DIR__, 2);
require_once $root . '/config/database.php';

$db = db();
$db->exec("CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `file` VARCHAR(255) NOT NULL UNIQUE,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$dir = __DIR__;
$files = glob($dir . '/*.sql');
sort($files);
foreach ($files as $f) {
    $name = basename($f);
    $chk = $db->prepare("SELECT id FROM migrations WHERE `file`=?");
    $chk->execute([$name]);
    if ($chk->fetch()) { echo "SKIP $name (already applied)\n"; continue; }
    echo "APPLY $name ...\n";
    $raw = file_get_contents($f);
    // Split on ; + newline (no procedures in our migrations)
    $stmts = preg_split('/;[\r\n]+/', $raw);
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    // Per-statement SET @var needs same connection - PDO keeps it.
    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $lines = array_filter(array_map('trim', explode("\n", $stmt)),
            fn($l) => $l !== '' && strpos($l, '--') !== 0 && strpos($l, '#') !== 0);
        if (empty($lines)) continue;
        try {
            $db->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Tolerate re-runs: duplicate column / duplicate key
            if (stripos($msg, 'Duplicate column') !== false) continue;
            if (stripos($msg, 'already exists') !== false) continue;
            if (stripos($msg, 'Duplicate entry') !== false) continue;
            fwrite(STDERR, "FAILED in $name: $msg\nSTATEMENT: " . substr($stmt, 0, 300) . "\n");
            exit(1);
        }
    }
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    $db->prepare("INSERT INTO migrations (`file`) VALUES (?)")->execute([$name]);
    echo "OK $name\n";
}
echo "Done.\n";
