<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$settings=$pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch();
if(!$settings){ $pdo->exec("INSERT INTO company_settings (id,company_name) VALUES ('default','TechCorp Inc.')"); $settings=$pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch(); }
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $company=trim($_POST['company_name']??''); $address=trim($_POST['address']??''); $phone=trim($_POST['phone']??''); $email=trim($_POST['email']??''); $logo=trim($_POST['logo_url']??''); $tz=trim($_POST['timezone']??'UTC'); $curr=trim($_POST['currency']??'USD'); $theme=trim($_POST['theme']??'teal');
    if($company){ $pdo->prepare("UPDATE company_settings SET company_name=?,address=?,phone=?,email=?,logo_url=?,timezone=?,currency=?,theme=? WHERE id=?")->execute([$company,$address?:null,$phone?:null,$email?:null,$logo?:null,$tz,$curr,$theme,$settings['id']]); flash('success','Settings saved'); }
    redirect(base_url('settings/'));
}
layout_header('Settings','Company configuration','teal','<a href="'.base_url('settings/users.php').'" class="btn btn-outline">Users</a> <a href="'.base_url('settings/roles.php').'" class="btn btn-outline">Roles</a> <a href="'.base_url('settings/audit-logs.php').'" class="btn btn-outline">Audit Logs</a> <a href="'.base_url('settings/notifications.php').'" class="btn btn-outline">Notifications</a>');
?>
<div class="card tab-notch tab-teal"><div class="card-content">
<form method="POST" style="display:grid;gap:0.75rem;max-width:40rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;font-weight:500;">Company Name *</label><input name="company_name" required class="input" value="<?= e($settings['company_name']) ?>"></div>
<div><label style="font-size:0.8125rem;">Address</label><input name="address" class="input" value="<?= e($settings['address']??'') ?>"></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;"><div><label style="font-size:0.8125rem;">Phone</label><input name="phone" class="input" value="<?= e($settings['phone']??'') ?>"></div><div><label style="font-size:0.8125rem;">Email</label><input name="email" type="email" class="input" value="<?= e($settings['email']??'') ?>"></div></div>
<div><label style="font-size:0.8125rem;">Logo URL</label><input name="logo_url" class="input" value="<?= e($settings['logo_url']??'') ?>"></div>
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
</div>
<button class="btn btn-primary">Save Changes</button>
</form>
</div></div>
<?php layout_footer(); ?>
