<?php
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/auth.php';
if (is_logged_in()) redirect(base_url('dashboard/'));
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    [$ok,$msg] = login_user($email,$password);
    if ($ok) { audit_log('LOGIN','auth', $_SESSION['user']['id']); redirect(base_url('dashboard/')); }
    else $error = $msg;
}
$theme = 'teal';
$customColor = '';
$siteName = 'HRMS';
$siteAbbr = 'HRMS';
$logoSrc = '';
try {
    $r=db()->query("SELECT * FROM company_settings LIMIT 1")->fetch();
    if($r && !empty($r['theme'])) $theme=$r['theme'];
    if($r && !empty($r['custom_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['custom_color'])) $customColor=(string)$r['custom_color'];
    if($r && !empty($r['company_name'])) $siteName=(string)$r['company_name'];
    if($r && !empty($r['abbreviation'])) $siteAbbr=(string)$r['abbreviation'];
    $logoSrc = company_logo_src($r['logo_url'] ?? null);
} catch(Exception $e){}
if ($theme === 'custom' && $customColor === '') $theme = 'teal';
$themeAttr = ($theme === 'teal' || $theme === 'custom') ? '' : $theme;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($themeAttr) ?>" class="h-full">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<?php if ($logoSrc !== ''): ?><link rel="icon" href="<?= e($logoSrc) ?>"><?php endif; ?>
<?php if ($theme === 'custom' && $customColor !== ''): ?>
<?= custom_theme_css($customColor) ?>
<?php endif; ?>
<style>body{margin:0;}</style>
</head>
<body>
<div style="display:flex;min-height:100vh;background:var(--background);">
  <div style="display:none;flex:1;flex-direction:column;align-items:center;justify-content:center;position:relative;overflow:hidden;padding:3rem;background:linear-gradient(135deg, color-mix(in oklab, var(--primary) 8%, transparent), transparent);" class="login-brand">
    <div style="position:absolute;inset:0;background:radial-gradient(ellipse at top right, color-mix(in oklab, var(--primary) 12%, transparent), transparent);"></div>
    <div style="position:absolute;right:-10rem;top:-10rem;width:30rem;height:30rem;border-radius:9999px;border:40px solid color-mix(in oklab, var(--primary) 5%, transparent);"></div>
    <div style="position:absolute;left:-8rem;bottom:-8rem;width:24rem;height:24rem;border-radius:9999px;border:32px solid color-mix(in oklab, var(--primary) 5%, transparent);"></div>
    <svg style="position:absolute;inset:0;width:100%;height:100%;opacity:0.03;" aria-hidden="true"><defs><pattern id="dot" width="24" height="24" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="currentColor"/></pattern></defs><rect width="100%" height="100%" fill="url(#dot)"/></svg>
    <div style="position:relative;z-index:1;text-align:center;" class="animate-fade-in">
      <?php if ($logoSrc !== ''): ?><img src="<?= e($logoSrc) ?>" alt="<?= e($siteName) ?>" style="margin:0 auto 2rem;height:4rem;width:auto;border-radius:0.75rem;"><?php else: ?><div style="margin:0 auto 2rem;width:4rem;height:4rem;border-radius:1rem;background:var(--primary);display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px color-mix(in oklab, var(--primary) 30%, transparent);color:var(--primary-foreground);font-weight:700;font-size:1.5rem;"><?= e(mb_substr($siteAbbr, 0, 1)) ?></div><?php endif; ?>
      <h1 style="font-family:var(--font-heading);font-size:2.25rem;font-weight:700;letter-spacing:-0.02em;"><?= e($siteName) ?></h1>
      <p style="max-width:20rem;color:var(--muted-foreground);font-size:0.9375rem;margin:0.5rem auto 0;line-height:1.5;">Human resource management system — manage your workforce with confidence.</p>
    </div>
  </div>
  <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:1.5rem;">
    <div style="width:100%;max-width:22rem;" class="animate-fade-in">
      <div style="text-align:center;margin-bottom:2rem;" class="mobile-brand">
        <?php if ($logoSrc !== ''): ?><img src="<?= e($logoSrc) ?>" alt="<?= e($siteName) ?>" style="margin:0 auto 1rem;height:3rem;width:auto;border-radius:0.5rem;"><?php else: ?><div style="margin:0 auto 1rem;width:3rem;height:3rem;border-radius:0.75rem;background:var(--primary);display:flex;align-items:center;justify-content:center;color:var(--primary-foreground);font-weight:700;"><?= e(mb_substr($siteAbbr, 0, 1)) ?></div><?php endif; ?>
        <h1 style="font-family:var(--font-heading);font-size:1.5rem;font-weight:700;"><?= e($siteName) ?></h1>
        <p style="margin-top:0.25rem;font-size:0.875rem;color:var(--muted-foreground);">Sign in to your account</p>
      </div>
      <div style="border:1px solid var(--border);background:var(--card);border-radius:1rem;padding:1.75rem;box-shadow:0 1px 3px oklch(0 0 0 / 0.05), 0 0 0 1px oklch(0 0 0 / 0.03);">
        <?php if($error): ?><div style="background:color-mix(in oklab, var(--destructive) 10%, transparent);color:var(--destructive);border:1px solid color-mix(in oklab, var(--destructive) 20%, transparent);padding:0.6rem 0.75rem;border-radius:0.5rem;font-size:0.8125rem;margin-bottom:1rem;"><?= e($error) ?></div><?php endif; ?>
        <form method="POST" style="display:flex;flex-direction:column;gap:1rem;">
          <div style="display:flex;flex-direction:column;gap:0.35rem;">
            <label for="email" style="font-size:0.8125rem;font-weight:500;">Email</label>
            <input id="email" name="email" type="email" placeholder="you@company.com" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>" class="input">
          </div>
          <div style="display:flex;flex-direction:column;gap:0.35rem;">
            <label for="password" style="font-size:0.8125rem;font-weight:500;">Password</label>
            <input id="password" name="password" type="password" placeholder="••••••••" required autocomplete="current-password" class="input">
          </div>
          <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer;">
            <input type="checkbox" style="width:1rem;height:1rem;accent-color:var(--primary);"> Remember me
          </label>
          <button type="submit" class="btn btn-primary" style="width:100%;height:2.25rem;">Sign in</button>
        </form>
        <div style="margin-top:1rem;padding:0.75rem;background:var(--muted);border-radius:0.5rem;font-size:0.75rem;line-height:1.5;color:var(--muted-foreground);">
          <strong>Demo accounts:</strong><br>
          admin@company.com / AdminPass123!<br>
          sarah.williams@company.com / Password123!
        </div>
      </div>
    </div>
  </div>
</div>
<style>@media(min-width:1024px){.login-brand{display:flex !important;} .mobile-brand{display:none !important;}}</style>
</body>
</html>
