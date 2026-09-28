<?php
// Helpers
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function base_url(string $path=''): string {
    // Assuming app is at /hrms-php
    $base = '/hrms-php';
    if ($path === '' || $path === '/') return $base . '/';
    return $base . '/' . ltrim($path, '/');
}
function redirect(string $url): void { header("Location: $url"); exit; }
function flash(string $key, string $msg=''): ?string {
    if ($msg !== '') { $_SESSION['_flash'][$key] = $msg; return null; }
    if (isset($_SESSION['_flash'][$key])) { $v = $_SESSION['_flash'][$key]; unset($_SESSION['_flash'][$key]); return $v; }
    return null;
}
function old(string $key, string $default=''): string { return e($_SESSION['_old'][$key] ?? $default); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): bool {
    $t = $_POST['csrf'] ?? $_POST['_csrf'] ?? '';
    return hash_equals($_SESSION['csrf'] ?? '', $t);
}
function audit_log(string $action, string $resource, ?string $resourceId=null, $details=null): void {
    try {
        $uid = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = db()->prepare("INSERT INTO audit_logs (id,user_id,action,resource,resource_id,details,ip_address,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $stmt->execute([uuid(), $uid, $action, $resource, $resourceId, $details ? json_encode($details) : null, $ip]);
    } catch (Exception $e) {}
}
function greeting(): string {
    $h = (int)date('G');
    if ($h < 12) return 'Good morning';
    if ($h < 18) return 'Good afternoon';
    return 'Good evening';
}
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach ($parts as $p) if ($p!=='') $out .= strtoupper($p[0]);
    return substr($out,0,2) ?: 'U';
}
function format_date($d, string $fmt='M d, Y'): string {
    if (!$d) return '-';
    try { return (new DateTime($d))->format($fmt); } catch(Exception $e){ return e($d); }
}
function badge_class(string $status): string {
    $map = [
        'ACTIVE'=>'badge-green','PRESENT'=>'badge-green','APPROVED'=>'badge-green','PAID'=>'badge-green','OPEN'=>'badge-green','HIRED'=>'badge-green',
        'ON_LEAVE'=>'badge-slate','DRAFT'=>'badge-slate','PROBATION'=>'badge-slate',
        'PENDING'=>'badge-brass','DEPARTMENT_APPROVED'=>'badge-blue','LATE'=>'badge-brass','HALF_DAY'=>'badge-brass','SCREENING'=>'badge-brass','INTERVIEW'=>'badge-brass','SUBMITTED'=>'badge-brass','PROCESSED'=>'badge-brass',
        'TERMINATED'=>'badge-brick','REJECTED'=>'badge-brick','ABSENT'=>'badge-brick','CLOSED'=>'badge-brick',
        'REVIEWED'=>'badge-green','OFFER'=>'badge-blue','APPLIED'=>'badge-slate',
    ];
    return $map[$status] ?? 'badge-slate';
}
