<?php
// Forced + voluntary password change.
// FIRST_LOGIN accounts can only reach this screen (enforced in auth.php).
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/auth.php';
if (!is_logged_in()) redirect(base_url('login.php'));

$pdo = db();
$stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1");
$stmt->execute([$_SESSION['user']['id']]);
$me = $stmt->fetch();
if (!$me) { logout_user(); redirect(base_url('login.php')); }
$isFirst = (($me['account_state'] ?? 'ACTIVE') === 'FIRST_LOGIN');
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $error = 'Invalid token. Please try again.'; }
    else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $me['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (($v = validate_new_password($new, (string)($me['employee_number'] ?? ''), $me['password'])) !== '') {
            $error = $v;
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $newState = $isFirst ? 'PENDING_201' : $me['account_state'];
            $pdo->prepare("UPDATE users SET password=?, account_state=?, failed_attempts=0, locked_until=NULL WHERE id=?")
                ->execute([$hash, $newState, $me['id']]);
            safe_session_regenerate();
            refresh_account_session();
            audit_log('PASSWORD_CHANGE', 'users', $me['id'], ['first_login' => $isFirst]);
            if ($isFirst) { flash('success', 'Password updated. Please complete your 201 file.'); redirect(base_url('onboarding/201-file.php')); }
            flash('success', 'Password changed.');
            redirect(base_url('profile/'));
        }
    }
}

// Minimal standalone shell (no app sidebar — FIRST_LOGIN must not see the app).
$theme = 'teal';
$customColor = '';
$siteName = 'HRMS';
try {
    $r = db()->query("SELECT theme, custom_color, company_name FROM company_settings LIMIT 1")->fetch();
    if ($r && !empty($r['theme'])) $theme = $r['theme'];
    if ($r && !empty($r['custom_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['custom_color'])) $customColor = (string)$r['custom_color'];
    if ($r && !empty($r['company_name'])) $siteName = (string)$r['company_name'];
} catch (Exception $e) {}
if ($theme === 'custom' && $customColor === '') $theme = 'teal';
$themeAttr = ($theme === 'teal' || $theme === 'custom') ? '' : $theme;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($themeAttr) ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password — <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<?php if ($theme === 'custom' && $customColor !== ''): ?>
<?= custom_theme_css($customColor) ?>
<?php endif; ?>
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:var(--background);">
  <div style="width:100%;max-width:24rem;">
    <div style="border:1px solid var(--border);background:var(--card);border-radius:1rem;padding:1.75rem;">
      <h1 style="font-family:var(--font-heading);font-size:1.25rem;font-weight:700;"><?= $isFirst ? 'Set your new password' : 'Change password' ?></h1>
      <p style="font-size:0.8125rem;color:var(--muted-foreground);margin:0.35rem 0 1rem;">
        <?= $isFirst ? 'You are using the default password. Create a personal password to continue.' : 'Enter your current password and choose a new one.' ?>
        <br>Employee Number: <strong style="font-family:var(--font-mono);"><?= e($me['employee_number'] ?? '-') ?></strong>
      </p>
      <?php if ($error): ?><div style="background:color-mix(in oklab, var(--destructive) 10%, transparent);color:var(--destructive);border:1px solid color-mix(in oklab, var(--destructive) 20%, transparent);padding:0.6rem 0.75rem;border-radius:0.5rem;font-size:0.8125rem;margin-bottom:1rem;"><?= e($error) ?></div><?php endif; ?>
      <form method="POST" style="display:grid;gap:0.75rem;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div><label style="font-size:0.8125rem;font-weight:500;">Current password</label><input name="current_password" type="password" required autocomplete="current-password" class="input"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">New password</label><input name="new_password" type="password" required autocomplete="new-password" class="input" placeholder="8+ characters, letter + number"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Confirm new password</label><input name="confirm_password" type="password" required autocomplete="new-password" class="input"></div>
        <button class="btn btn-primary" style="width:100%;"><?= $isFirst ? 'Save and continue' : 'Change password' ?></button>
      </form>
      <?php if (!$isFirst): ?><div style="margin-top:0.75rem;text-align:center;"><a href="<?= base_url('dashboard/') ?>" style="font-size:0.8125rem;">Back to dashboard</a></div>
      <?php else: ?><div style="margin-top:0.75rem;text-align:center;"><a href="<?= base_url('logout.php') ?>" style="font-size:0.8125rem;">Sign out</a></div><?php endif; ?>
      <p style="font-size:0.72rem;color:var(--muted-foreground);margin-top:0.75rem;">Password must be 8+ characters with a letter and a number, and cannot be your employee number or current password.</p>
    </div>
  </div>
</div>
</body>
</html>
