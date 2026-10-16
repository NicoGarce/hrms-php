<?php
// FTP deploy for HRMS-PHP (shared hosting, e.g. InfinityFree / cPanel).
//
// Credentials come ONLY from environment variables — never commit them:
//   $env:FTP_HOST="ftpupload.example.com"   # required
//   $env:FTP_USER="user"                    # required
//   $env:FTP_PASS="secret"                  # required
//   $env:FTP_PORT="21"                      # optional, default 21
//   $env:FTP_PATH="/htdocs"                # optional, remote app dir (default "/")
//   $env:FTP_SSL="0"                        # optional, 1 = explicit FTPS
//   $env:FTP_PASSIVE="1"                    # optional, default 1
//
// Usage:
//   php deploy/ftp-deploy.php --dry-run        # list files, no connection
//   php deploy/ftp-deploy.php                  # full mirror upload
//   php deploy/ftp-deploy.php --skip-unchanged # skip files with matching size
//
// Notes:
// - database.sql IS uploaded (first page load auto-installs schema + seed).
// - Local user uploads (assets/uploads/*) are NOT pushed; the dir is created.
// - .git/ and deploy/ itself are never uploaded.
// - After upload: create the MySQL DB in the host panel, then set DB_HOST /
//   DB_NAME / DB_USER / DB_PASS env vars if supported, otherwise edit
//   config/config.php on the server.

error_reporting(E_ALL);
$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv ?? [], true);
$skipUnchanged = in_array('--skip-unchanged', $argv ?? [], true);

$host = getenv('FTP_HOST') ?: '';
$user = getenv('FTP_USER') ?: '';
$pass = getenv('FTP_PASS') ?: '';
$port = (int)(getenv('FTP_PORT') ?: 21);
$remoteBase = rtrim(getenv('FTP_PATH') ?: '/', '/');
if ($remoteBase === '') $remoteBase = '/';
$useSsl = getenv('FTP_SSL') === '1';
$passive = getenv('FTP_PASSIVE') !== '0';

if (!$dryRun && ($host === '' || $user === '' || $pass === '')) {
    fwrite(STDERR, "Set FTP_HOST, FTP_USER and FTP_PASS env vars first.\n");
    exit(1);
}

// Collect files: everything except .git, deploy/, and uploaded user content.
$excludeDirs = ['.git', 'deploy'];
$files = [];
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iter as $f) {
    /** @var SplFileInfo $f */
    if (!$f->isFile()) continue;
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
    $top = explode('/', $rel)[0];
    if (in_array($top, $excludeDirs, true)) continue;
    if (strpos($rel, 'assets/uploads/') === 0) continue; // user content, not code
    if ($rel === 'config/config.php') continue; // NEVER overwrite: live DB credentials live here
    if (preg_match('/check_.*_tmp\.php$/', $rel)) continue;
    $base = basename($rel);
    if (in_array($base, ['Thumbs.db', 'Desktop.ini', '.DS_Store'], true)) continue;
    $files[] = $rel;
}
sort($files);
printf("%d files to deploy.\n", count($files));

if ($dryRun) {
    foreach ($files as $rel) echo "  $rel\n";
    echo "Dry run — nothing uploaded.\n";
    exit(0);
}

$conn = $useSsl ? @ftp_ssl_connect($host, $port) : @ftp_connect($host, $port);
if (!$conn) { fwrite(STDERR, "Cannot connect to $host:$port\n"); exit(1); }
if (!@ftp_login($conn, $user, $pass)) { fwrite(STDERR, "Login failed for $user\n"); exit(1); }
ftp_pasv($conn, $passive);
ftp_set_option($conn, FTP_TIMEOUT_SEC, 60); // hang -> fail fast, never stall forever

$made = []; // created remote dirs
$mkdirs = function (string $dir) use ($conn, &$made): void {
    if (isset($made[$dir])) return;
    $parts = explode('/', trim($dir, '/'));
    $path = '';
    foreach ($parts as $p) {
        $path .= '/' . $p;
        if (isset($made[$path])) continue;
        if (@ftp_chdir($conn, $path)) { $made[$path] = true; continue; }
        if (@ftp_mkdir($conn, $path)) $made[$path] = true;
    }
    $made[$dir] = true;
};

$ok = 0; $failed = []; $skipped = 0; $n = 0;
$curDir = null;
foreach ($files as $rel) {
    $n++;
    $remote = $remoteBase === '/' ? "/$rel" : "$remoteBase/$rel";
    $remoteDir = dirname($remote);
    // One chdir per directory (files are sorted, so same-dir runs stay put).
    if ($remoteDir !== $curDir) {
        if ($remoteDir !== '/' && $remoteDir !== '.') $mkdirs($remoteDir);
        if (@ftp_chdir($conn, $remoteDir)) $curDir = $remoteDir;
        else { $failed[] = "$rel (chdir)"; continue; }
    }
    $local = $root . '/' . $rel;
    if ($skipUnchanged) {
        $remoteSize = @ftp_size($conn, $remote);
        if ($remoteSize >= 0 && $remoteSize === filesize($local)) { $skipped++; continue; }
    }
    // Relative put against the current dir; fall back to absolute path.
    if (@ftp_put($conn, basename($rel), $local, FTP_BINARY)
        || @ftp_put($conn, $remote, $local, FTP_BINARY)) $ok++;
    else $failed[] = $rel;
    if ($n % 5 === 0 || $n === count($files)) { echo "  [$n/" . count($files) . "] $ok ok, " . count($failed) . " failed\r\n"; @ob_flush(); @flush(); }
}
// Writable uploads dir for avatars / documents / memos.
$mkdirs($remoteBase === '/' ? '/assets/uploads' : "$remoteBase/assets/uploads");

ftp_close($conn);
echo "Done: $ok uploaded" . ($skipUnchanged ? ", $skipped unchanged skipped" : "") . ", " . count($failed) . " failed.\n";
foreach ($failed as $f) echo "  FAILED: $f\n";
exit(count($failed) ? 1 : 0);
