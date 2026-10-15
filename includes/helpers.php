<?php
// Helpers
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function base_url(string $path=''): string {
    // Auto-detect the app's subfolder so the same code works on
    // localhost (/hrms-php/...) and on live hosts serving from / (e.g. InfinityFree htdocs).
    static $base = null;
    if ($base === null) {
        $base = '';
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', (string)(realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT'])) : '';
        $appRoot = str_replace('\\', '/', (string)(realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
        if ($docRoot !== '' && strpos($appRoot, rtrim($docRoot, '/')) === 0) {
            $base = substr($appRoot, strlen(rtrim($docRoot, '/')));
        }
        $base = rtrim((string)$base, '/');
        // CLI / unusual server setups: fall back to sniffing SCRIPT_NAME
        if ($base === '' && isset($_SERVER['SCRIPT_NAME']) && strpos((string)$_SERVER['SCRIPT_NAME'], '/hrms-php/') !== false) {
            $base = '/hrms-php';
        }
    }
    if ($path === '' || $path === '/') return $base . '/';
    if ($base === '') return '/' . ltrim($path, '/');
    return $base . '/' . ltrim($path, '/');
}
function redirect(string $url): void { header("Location: $url"); exit; }
/** Resolve a stored logo path/URL to a usable src. Returns '' when none. */
function company_logo_src(?string $logo): string {
    $logo = trim((string)$logo);
    if ($logo === '') return '';
    if (preg_match('#^(https?://|data:)#i', $logo)) return $logo;
    return base_url(ltrim($logo, '/'));
}
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

/**
 * Build a complete theme palette from a custom brand hex color.
 * Works like the built-in presets (background tint, sidebar, charts, …),
 * not just buttons: every teal hue is rotated by the picked color's hue
 * shift, keeping the theme's designed contrast relationships. Buttons and
 * active states keep the exact picked hex; text on them auto-flips for
 * readability. Returns a <style> tag (or '' for invalid input).
 */
function custom_theme_css(string $hex): string {
    $hex = strtolower(trim($hex));
    if (!preg_match('/^#[0-9a-f]{6}$/', $hex)) return '';
    $r = hexdec(substr($hex, 1, 2)) / 255;
    $g = hexdec(substr($hex, 3, 2)) / 255;
    $b = hexdec(substr($hex, 5, 2)) / 255;
    $mx = max($r, $g, $b); $mn = min($r, $g, $b);
    $delta = 0.0; // hue rotation relative to the teal base (primary hue 200)
    if ($mx !== $mn) {
        $d = $mx - $mn;
        if ($mx === $r) $h = 60 * fmod((($g - $b) / $d), 6);
        elseif ($mx === $g) $h = 60 * ((($b - $r) / $d) + 2);
        else $h = 60 * ((($r - $g) / $d) + 4);
        if ($h < 0) $h += 360;
        $delta = $h - 200;
    }
    $rot = function ($base) use ($delta): string {
        $v = fmod($base + $delta, 360);
        if ($v < 0) $v += 360;
        return rtrim(rtrim(number_format($v, 1), '0'), '.');
    };
    $H = $rot(200);
    $lum = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    $fg = $lum > 0.45 ? "oklch(0.15 0.015 {$H})" : "oklch(0.98 0.005 {$H})";
    $darkFg = "oklch(0.15 0.012 {$H})";
    $light = ":root{"
        . "--background:oklch(0.975 0.004 {$rot(210)});--foreground:oklch(0.22 0.02 {$rot(250)});"
        . "--card:oklch(1 0 0);--card-foreground:oklch(0.22 0.02 {$rot(250)});"
        . "--popover:oklch(1 0 0);--popover-foreground:oklch(0.22 0.02 {$rot(250)});"
        . "--primary:{$hex};--primary-foreground:{$fg};"
        . "--secondary:oklch(0.94 0.008 {$rot(210)});--secondary-foreground:oklch(0.22 0.02 {$rot(250)});"
        . "--muted:oklch(0.94 0.006 {$rot(210)});--muted-foreground:oklch(0.5 0.02 {$rot(240)});"
        . "--accent:oklch(0.91 0.02 {$H});--accent-foreground:oklch(0.3 0.05 {$H});"
        . "--border:oklch(0.89 0.01 {$rot(210)});--input:oklch(0.89 0.01 {$rot(210)});--ring:{$hex};"
        . "--chart-1:oklch(0.5 0.08 {$H});--chart-2:oklch(0.68 0.11 {$rot(75)});--chart-3:oklch(0.55 0.1 {$rot(150)});"
        . "--chart-4:oklch(0.55 0.12 {$rot(30)});--chart-5:oklch(0.55 0.02 {$rot(240)});"
        . "--sidebar:oklch(0.19 0.02 {$rot(240)});--sidebar-foreground:oklch(0.94 0.01 {$rot(90)});"
        . "--sidebar-primary:{$hex};--sidebar-primary-foreground:{$fg};"
        . "--sidebar-accent:oklch(0.25 0.02 {$rot(240)});--sidebar-accent-foreground:oklch(0.96 0.01 {$rot(90)});"
        . "--sidebar-ring:{$hex};}";
    $dark = ".dark{"
        . "--background:oklch(0.17 0.012 {$rot(240)});--foreground:oklch(0.95 0.006 {$H});"
        . "--card:oklch(0.22 0.014 {$rot(240)});--card-foreground:oklch(0.95 0.006 {$H});"
        . "--popover:oklch(0.22 0.014 {$rot(240)});--popover-foreground:oklch(0.95 0.006 {$H});"
        . "--primary:color-mix(in oklab, {$hex} 72%, white);--primary-foreground:{$darkFg};"
        . "--secondary:oklch(0.28 0.015 {$rot(240)});--secondary-foreground:oklch(0.95 0.006 {$H});"
        . "--muted:oklch(0.26 0.014 {$rot(240)});--muted-foreground:oklch(0.66 0.015 {$rot(240)});"
        . "--accent:oklch(0.28 0.03 {$H});--accent-foreground:oklch(0.95 0.006 {$H});"
        . "--ring:{$hex};"
        . "--chart-1:oklch(0.62 0.09 {$H});--chart-2:oklch(0.72 0.12 {$rot(75)});--chart-3:oklch(0.62 0.11 {$rot(150)});"
        . "--chart-4:oklch(0.62 0.13 {$rot(30)});--chart-5:oklch(0.65 0.02 {$rot(240)});"
        . "--sidebar:oklch(0.12 0.012 {$rot(240)});--sidebar-foreground:oklch(0.93 0.008 {$rot(90)});"
        . "--sidebar-primary:color-mix(in oklab, {$hex} 72%, white);--sidebar-primary-foreground:{$darkFg};"
        . "--sidebar-accent:oklch(0.18 0.015 {$rot(240)});--sidebar-accent-foreground:oklch(0.95 0.006 {$H});"
        . "--sidebar-ring:{$hex};}";
    return '<style>' . $light . $dark . '</style>';
}
