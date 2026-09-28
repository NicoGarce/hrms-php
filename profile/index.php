<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$userId=$_SESSION['user']['id'];
$stmt=$pdo->prepare("SELECT e.*, d.name as dept_name, p.title as pos_title, u.email, r.name as role_name, ep.phone, ep.address, ep.emergency_contact, ep.date_of_birth FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN users u ON u.id=e.user_id LEFT JOIN roles r ON r.id=u.role_id LEFT JOIN employee_profiles ep ON ep.employee_id=e.id WHERE e.user_id=? LIMIT 1");
$stmt->execute([$userId]); $emp=$stmt->fetch();
if(!$emp){
    // admin without employee record - show user info only
    $u=$pdo->prepare("SELECT u.email, r.name as role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?"); $u->execute([$userId]); $usr=$u->fetch();
    layout_header('Profile','Your account','teal');
    echo '<div class="card tab-notch tab-teal"><div class="card-content"><p style="font-size:0.875rem;">No employee profile linked to this account.</p><div style="margin-top:0.75rem;font-size:0.875rem;"><div>Email: '.e($usr['email']).'</div><div>Role: '.e($usr['role_name']).'</div></div></div></div>'; layout_footer(); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $phone=trim($_POST['phone']??''); $address=trim($_POST['address']??'');
    $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,phone,address) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE phone=VALUES(phone), address=VALUES(address)")->execute([uuid(),$emp['id'],$phone?:null,$address?:null]);
    // ensure profile exists
    $pdo->prepare("UPDATE employee_profiles SET phone=?, address=? WHERE employee_id=?")->execute([$phone?:null,$address?:null,$emp['id']]);
    flash('success','Profile updated');
    redirect(base_url('profile/'));
}
layout_header('Profile','Manage your personal information','teal');
$initials=strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
?>
<div style="display:grid;gap:1rem;max-width:48rem;">
<div class="card tab-notch tab-teal"><div class="card-content" style="display:flex;gap:1rem;align-items:center;">
<div style="width:3rem;height:3rem;border-radius:9999px;background:var(--primary);color:var(--primary-foreground);display:flex;align-items:center;justify-content:center;font-weight:700;"><?= e($initials) ?></div>
<div><div style="font-weight:700;"><?= e($emp['first_name'].' '.$emp['last_name']) ?></div><div style="font-size:0.8125rem;color:var(--muted-foreground);"><?= e($emp['email']) ?> • <?= e($emp['role_name']) ?></div><div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e($emp['dept_name']??'-') ?> • <?= e($emp['pos_title']??'-') ?></div></div>
</div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Details</div></div><div class="card-content" style="display:grid;gap:0.5rem;font-size:0.875rem;">
<div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Employee Code</span><span style="font-family:var(--font-mono);"><?= e($emp['employee_code']) ?></span></div>
<div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Hire Date</span><span><?= e(date('M d, Y', strtotime($emp['hire_date']))) ?></span></div>
<div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Status</span><span class="badge <?= badge_class($emp['status']) ?>"><?= e($emp['status']) ?></span></div>
<div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">DOB</span><span><?= $emp['date_of_birth']?e(date('M d, Y', strtotime($emp['date_of_birth']))):'-' ?></span></div>
</div></div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Edit Contact</div></div><div class="card-content">
<form method="POST" style="display:grid;gap:0.75rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;">Phone</label><input name="phone" class="input" value="<?= e($emp['phone']??'') ?>"></div>
<div><label style="font-size:0.8125rem;">Address</label><input name="address" class="input" value="<?= e($emp['address']??'') ?>"></div>
<button class="btn btn-primary">Save</button>
</form>
<?php if($emp['emergency_contact']): ?><div style="margin-top:0.75rem;font-size:0.8125rem;"><span style="color:var(--muted-foreground);">Emergency Contact:</span> <?= e($emp['emergency_contact']) ?></div><?php endif; ?>
</div></div>
</div>
</div>
<?php layout_footer(); ?>
