<?php
// Authorized document download gate (Part C security).
// Local uploads in assets/uploads/ are NOT linked directly anymore so that
// only the owner, HR Admins and Super Admins can fetch them.
require_once __DIR__.'/../includes/auth.php';
require_login();
$pdo = db();
$id = $_GET['id'] ?? '';
if ($id === '') { http_response_code(400); exit('Missing document'); }
$stmt = $pdo->prepare("SELECT d.*, e.user_id FROM documents d JOIN employees e ON e.id=d.employee_id WHERE d.id=? LIMIT 1");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('Not found'); }
$role = $_SESSION['user']['role'] ?? '';
$isAdmin = in_array($role, ['SUPER_ADMINISTRATOR', 'HR_ADMINISTRATOR'], true);
if (!$isAdmin && $doc['user_id'] !== $_SESSION['user']['id']) { http_response_code(403); exit('Forbidden'); }
$url = (string)($doc['file_url'] ?? '');
// Legacy external URLs (e.g. applicant resume links) redirect out.
if (preg_match('#^https?://#i', $url) && strpos($url, 'assets/uploads/') === false) { header('Location: ' . $url); exit; }
$path = __DIR__ . '/../assets/uploads/' . basename(parse_url($url, PHP_URL_PATH));
if (!is_file($path)) { http_response_code(404); exit('File missing'); }
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($doc['name'])) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
