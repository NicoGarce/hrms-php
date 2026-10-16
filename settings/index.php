<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
// Self-migrate for existing installs (fresh installs already have these via database.sql)
foreach (['custom_color' => 'VARCHAR(7) NULL', 'sidebar_sections' => 'TEXT NULL', 'abbreviation' => 'VARCHAR(20) NULL'] as $col => $def) {
    try {
        $exists = $pdo->query("SHOW COLUMNS FROM company_settings LIKE '" . $col . "'")->fetch();
        if (!$exists) $pdo->exec("ALTER TABLE company_settings ADD COLUMN `" . $col . "` " . $def);
    } catch (Exception $e) {}
}
$settings=$pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch();
if(!$settings){ $pdo->exec("INSERT INTO company_settings (id,company_name) VALUES ('default','TechCorp Inc.')"); $settings=$pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch(); }
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $company=trim($_POST['company_name']??''); $address=trim($_POST['address']??''); $phone=trim($_POST['phone']??''); $email=trim($_POST['email']??''); $tz=trim($_POST['timezone']??'UTC'); $curr=trim($_POST['currency']??'USD'); $theme=trim($_POST['theme']??'teal');
    $abbr=strtoupper(trim($_POST['abbreviation']??''));
    if(strlen($abbr) > 12) $abbr = substr($abbr, 0, 12);
    $logo=$settings['logo_url'] ?? null;
    if(isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE){
        if($_FILES['logo']['error'] === UPLOAD_ERR_OK){
            $ext=strtolower(pathinfo($_FILES['logo']['name'] ?? '', PATHINFO_EXTENSION));
            $okExt = in_array($ext, ['png','webp'], true);
            $mime = '';
            try {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($_FILES['logo']['tmp_name']);
            } catch(Exception $e) {}
            if(!$okExt || !in_array($mime, ['image/png','image/webp'], true)){
                flash('error','Logo must be a PNG or WebP image.');
                redirect(base_url('settings/'));
            }
            if(($_FILES['logo']['size'] ?? 0) > 2*1024*1024){
                flash('error','Logo must be under 2 MB.');
                redirect(base_url('settings/'));
            }
            $destDir=__DIR__.'/../assets/img';
            if(!is_dir($destDir)) mkdir($destDir,0777,true);
            $dest=$destDir.'/logo.'.$ext;
            if(move_uploaded_file($_FILES['logo']['tmp_name'], $dest)){
                foreach(['png','webp'] as $old){ if($old!==$ext && file_exists($destDir.'/logo.'.$old)) @unlink($destDir.'/logo.'.$old); }
                $logo='assets/img/logo.'.$ext.'?v='.time();
            } else {
                flash('error','Logo upload failed.');
                redirect(base_url('settings/'));
            }
        } else {
            flash('error','Logo upload failed.');
            redirect(base_url('settings/'));
        }
    }
    $allowedThemes = ['teal','warm','slate','forest','dusk','custom'];
    if(!in_array($theme, $allowedThemes, true)) $theme = 'teal';
    $customColor = trim($_POST['custom_color'] ?? ($settings['custom_color'] ?? ''));
    if(!preg_match('/^#[0-9a-fA-F]{6}$/', $customColor)) $customColor = null;
    if($theme === 'custom' && $customColor === null) $customColor = '#1a6b73';
    $allSections = array_keys(nav_section_map());
    $picked = array_values(array_intersect($allSections, array_map('strval', (array)($_POST['sections'] ?? []))));
    if(!in_array('ADMIN', $picked, true)) $picked[] = 'ADMIN'; // always on so settings stay reachable
    if($company){ $pdo->prepare("UPDATE company_settings SET company_name=?,abbreviation=?,address=?,phone=?,email=?,logo_url=?,timezone=?,currency=?,theme=?,custom_color=?,sidebar_sections=? WHERE id=?")->execute([$company,$abbr?:null,$address?:null,$phone?:null,$email?:null,$logo,$tz,$curr,$theme,$customColor,json_encode($picked),$settings['id']]); flash('success','Settings saved'); }
    redirect(base_url('settings/'));
}
$enabledSections = sidebar_enabled_sections();
$currentCustomColor = (!empty($settings['custom_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$settings['custom_color'])) ? $settings['custom_color'] : '#1a6b73';
layout_header();
?>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
<a href="<?= base_url('user-management/') ?>" class="btn btn-outline btn-sm">User Management</a> <a href="<?= base_url('settings/roles.php') ?>" class="btn btn-outline btn-sm">Roles</a> <a href="<?= base_url('settings/audit-logs.php') ?>" class="btn btn-outline btn-sm">Audit Logs</a> <a href="<?= base_url('settings/notifications.php') ?>" class="btn btn-outline btn-sm">Notifications</a>
</div>
<div class="card tab-notch tab-teal"><div class="card-content">
<form method="POST" enctype="multipart/form-data" style="display:grid;gap:0.75rem;max-width:40rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;font-weight:500;">Company Name *</label><input name="company_name" required class="input" value="<?= e($settings['company_name']) ?>"></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Abbreviation</label><input name="abbreviation" class="input" maxlength="12" placeholder="e.g. SCASCI" value="<?= e($settings['abbreviation']??'') ?>" style="max-width:12rem;text-transform:uppercase;"><p style="font-size:0.75rem;color:var(--muted-foreground);margin:0.25rem 0 0;">Shown in the sidebar header.</p></div>
<div><label style="font-size:0.8125rem;">Address</label><input name="address" class="input" value="<?= e($settings['address']??'') ?>"></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;"><div><label style="font-size:0.8125rem;">Phone</label><input name="phone" class="input" value="<?= e($settings['phone']??'') ?>"></div><div><label style="font-size:0.8125rem;">Email</label><input name="email" type="email" class="input" value="<?= e($settings['email']??'') ?>"></div></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Logo (PNG or WebP, max 2 MB)</label>
<?php $curLogo = company_logo_src($settings['logo_url'] ?? null); ?>
<?php if($curLogo): ?><div style="margin:0.375rem 0;"><img src="<?= e($curLogo) ?>" alt="Current logo" style="height:2.5rem;width:auto;border-radius:0.375rem;border:1px solid var(--border);background:var(--muted);"></div><?php endif; ?>
<input type="file" name="logo" accept=".png,.webp,image/png,image/webp" class="input"></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;"><div><label style="font-size:0.8125rem;">Timezone</label><input name="timezone" class="input" value="<?= e($settings['timezone']) ?>"></div><div><label style="font-size:0.8125rem;">Currency</label><input name="currency" class="input" value="<?= e($settings['currency']) ?>"></div></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Theme</label>
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0.5rem;margin-top:0.5rem;">
<?php foreach(['teal'=>'#1a6b73','warm'=>'#9a7b4f','slate'=>'#5c5f6b','forest'=>'#2d6e4f','dusk'=>'#4f46b3'] as $k=>$c): ?>
<label style="cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:0.25rem;padding:0.5rem;border:2px solid <?= $settings['theme']==$k?'var(--primary)':'var(--border)' ?>;border-radius:0.5rem;">
<input type="radio" name="theme" value="<?= e($k) ?>" <?= $settings['theme']==$k?'checked':'' ?> style="accent-color:var(--primary);">
<span style="width:2rem;height:1.5rem;border-radius:0.25rem;background:<?= e($c) ?>;"></span>
<span style="font-size:0.7rem;"><?= e(ucfirst($k)) ?></span>
</label>
<?php endforeach; ?>
</div>
<div style="margin-top:0.5rem;">
<label style="cursor:pointer;display:flex;align-items:center;gap:0.75rem;padding:0.5rem;border:2px solid <?= $settings['theme']=='custom'?'var(--primary)':'var(--border)' ?>;border-radius:0.5rem;">
<input type="radio" name="theme" value="custom" <?= $settings['theme']=='custom'?'checked':'' ?> style="accent-color:var(--primary);">
<input type="color" name="custom_color" value="<?= e($currentCustomColor) ?>" oninput="this.form.theme.value='custom'" style="width:2.5rem;height:1.75rem;padding:0;border:1px solid var(--border);border-radius:0.25rem;background:transparent;cursor:pointer;">
<span style="font-size:0.8125rem;">Custom color <span style="color:var(--muted-foreground);">(pick any brand color)</span></span>
</label>
</div>
</div>
<div><label style="font-size:0.8125rem;font-weight:500;">Sidebar Sections</label>
<p style="font-size:0.75rem;color:var(--muted-foreground);margin:0.25rem 0 0.5rem;">Untick a section to hide it from the sidebar and block its pages for everyone.</p>
<div style="display:grid;gap:0.5rem;">
<?php foreach(nav_section_map() as $section => $titles): ?>
<label style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0.625rem;border:1px solid var(--border);border-radius:0.5rem;cursor:pointer;font-size:0.8125rem;">
<?php if($section==='ADMIN'): ?>
<input type="checkbox" checked disabled style="width:1rem;height:1rem;accent-color:var(--primary);">
<input type="hidden" name="sections[]" value="ADMIN">
<?php else: ?>
<input type="checkbox" name="sections[]" value="<?= e($section) ?>" <?= in_array($section,$enabledSections,true)?'checked':'' ?> style="width:1rem;height:1rem;accent-color:var(--primary);">
<?php endif; ?>
<span style="font-weight:600;"><?= e($section) ?></span>
<span style="color:var(--muted-foreground);font-size:0.75rem;"><?= e(implode(', ', $titles)) ?></span>
<?php if($section==='ADMIN'): ?><span style="margin-left:auto;font-size:0.6875rem;color:var(--muted-foreground);">Always on</span><?php endif; ?>
</label>
<?php endforeach; ?>
</div>
</div>
<button class="btn btn-primary">Save Changes</button>
</form>
</div></div>
<?php layout_footer(); ?>
