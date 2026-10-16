<?php
// Authorized memo download gate (announcement attachments).
require_once __DIR__.'/../includes/auth.php';
require_login();
$pdo = db();
$id = $_GET['id'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id=? AND (expires_at IS NULL OR expires_at >= NOW()) LIMIT 1");
$stmt->execute([$id]);
$ann = $stmt->fetch();
// HR/Super can also fetch expired memos they manage.
if (!$ann && in_array($_SESSION['user']['role'] ?? '', ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'], true)) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $ann = $stmt->fetch();
}
if (!$ann || empty($ann['memo_file'])) { http_response_code(404); exit('Not found'); }
$url = (string)$ann['memo_file'];
if (preg_match('#^https?://#i', $url)) { header('Location: ' . $url); exit; }
$path = __DIR__ . '/../assets/uploads/' . basename(parse_url($url, PHP_URL_PATH));
if (!is_file($path)) { http_response_code(404); exit('File missing'); }
$finfo = new finfo(FILEINFO_MIME_TYPE);
header('Content-Type: ' . ($finfo->file($path) ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($path)) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
