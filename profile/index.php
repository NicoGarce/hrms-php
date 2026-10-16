<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$userId=$_SESSION['user']['id'];
$stmt=$pdo->prepare("SELECT e.*, d.name as dept_name, p.title as pos_title, u.email, u.employee_number, r.name as role_name, ep.phone, ep.address, ep.emergency_contact, ep.date_of_birth, ep.avatar, ep.is_201_submitted FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN users u ON u.id=e.user_id LEFT JOIN roles r ON r.id=u.role_id LEFT JOIN employee_profiles ep ON ep.employee_id=e.id WHERE e.user_id=? AND e.deleted_at IS NULL LIMIT 1");
$stmt->execute([$userId]); $emp=$stmt->fetch();
if(!$emp){
    // Account without employee record (e.g. Super Admin): account + password only.
    $u=$pdo->prepare("SELECT u.email, u.employee_number, r.name as role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?"); $u->execute([$userId]); $usr=$u->fetch();
    if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf() && isset($_POST['change_password'])){
        $me=$pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$userId]); $me=$me->fetch();
        $err='';
        if(!password_verify($_POST['current_password']??'', $me['password'])) $err='Current password is incorrect.';
        elseif(($_POST['new_password']??'')!==($_POST['confirm_password']??'')) $err='New passwords do not match.';
        elseif(($v=validate_new_password($_POST['new_password']??'', (string)($me['employee_number']??''), $me['password']))!=='') $err=$v;
        if($err){ flash('error',$err); }
        else{
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['new_password'],PASSWORD_BCRYPT),$userId]);
            safe_session_regenerate();
            audit_log('PASSWORD_CHANGE','users',$userId,['via'=>'profile']);
            flash('success','Password changed.');
        }
        redirect(base_url('profile/'));
    }
    layout_header('Profile','Your account','teal');
    echo '<div class="card tab-notch tab-teal"><div class="card-content" style="display:grid;gap:0.5rem;font-size:0.875rem;"><div>No employee profile linked to this account.</div><div>Employee Number: <strong style="font-family:var(--font-mono);">'.e($usr['employee_number']??'-').'</strong></div><div>Email: '.e($usr['email']).'</div><div>Role: '.e($usr['role_name']).'</div></div></div>';
    echo '<div class="card tab-notch tab-slate" style="margin-top:1rem;"><div class="card-header"><div class="card-title">Change password</div></div><div class="card-content"><form method="POST" style="display:grid;gap:0.75rem;max-width:24rem;"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><div><label style="font-size:0.8125rem;">Current password</label><input name="current_password" type="password" required class="input"></div><div><label style="font-size:0.8125rem;">New password</label><input name="new_password" type="password" required class="input"></div><div><label style="font-size:0.8125rem;">Confirm new password</label><input name="confirm_password" type="password" required class="input"></div><button name="change_password" class="btn btn-primary">Change password</button></form></div></div>';
    layout_footer(); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    if(isset($_POST['save_contact'])){
        $phone=trim($_POST['phone']??''); $address=trim($_POST['address']??'');
        $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,phone,address) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE phone=VALUES(phone), address=VALUES(address)")->execute([uuid(),$emp['id'],$phone?:null,$address?:null]);
        audit_log('UPDATE','employee_profiles',$emp['id'],['contact'=>true]);
        flash('success','Contact info updated');
        redirect(base_url('profile/'));
    }
    if(isset($_POST['upload_avatar']) && isset($_FILES['avatar']) && $_FILES['avatar']['error']!==UPLOAD_ERR_NO_FILE){
        $f=$_FILES['avatar']; $err='';
        if($f['error']!==UPLOAD_ERR_OK) $err='Upload failed.';
        elseif(($f['size']??0)>2*1024*1024) $err='Image too large (max 2 MB).';
        else{
            $ext=strtolower(pathinfo($f['name']??'', PATHINFO_EXTENSION));
            if(!in_array($ext,['png','jpg','jpeg','webp'],true)) $err='Image must be PNG, JPG or WebP.';
            elseif(@getimagesize($f['tmp_name'])===false) $err='Invalid image file.';
        }
        if($err){ flash('error',$err); redirect(base_url('profile/')); }
        $ext=strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $fname=uuid().'.'.$ext;
        $dest=__DIR__.'/../assets/uploads/'.$fname;
        if(!is_dir(dirname($dest))) mkdir(dirname($dest),0777,true);
        move_uploaded_file($f['tmp_name'], $dest);
        $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,avatar) VALUES (?,?,?) ON DUPLICATE KEY UPDATE avatar=VALUES(avatar)")->execute([uuid(),$emp['id'],base_url('assets/uploads/'.$fname)]);
        audit_log('UPLOAD','employee_profiles',$emp['id'],['avatar'=>true]);
        flash('success','Profile image updated');
        redirect(base_url('profile/'));
    }
    if(isset($_POST['change_password'])){
        $me=$pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$userId]); $me=$me->fetch();
        $err='';
        if(!password_verify($_POST['current_password']??'', $me['password'])) $err='Current password is incorrect.';
        elseif(($_POST['new_password']??'')!==($_POST['confirm_password']??'')) $err='New passwords do not match.';
        elseif(($v=validate_new_password($_POST['new_password']??'', (string)($me['employee_number']??''), $me['password']))!=='') $err=$v;
        if($err){ flash('error',$err); }
        else{
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['new_password'],PASSWORD_BCRYPT),$userId]);
            safe_session_regenerate();
            audit_log('PASSWORD_CHANGE','users',$userId,['via'=>'profile']);
            flash('success','Password changed.');
        }
        redirect(base_url('profile/'));
    }
}

$progress=get_201_progress($emp['id']);
$missing=array_values(array_filter($progress['items'], fn($i)=>!$i['done']));
$docs=$pdo->prepare("SELECT * FROM documents WHERE employee_id=? ORDER BY created_at DESC"); $docs->execute([$emp['id']]); $docList=$docs->fetchAll();
$eduList=$pdo->prepare("SELECT * FROM employee_education WHERE employee_id=? ORDER BY created_at DESC"); $eduList->execute([$emp['id']]); $eduList=$eduList->fetchAll();
$expList=$pdo->prepare("SELECT * FROM employee_experience WHERE employee_id=? ORDER BY start_date DESC"); $expList->execute([$emp['id']]); $expList=$expList->fetchAll();
$avatarSrc=$emp['avatar']??'';
$initials=strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
layout_header('Profile','Your employment profile and 201 file status','teal');
if($missing || !$avatarSrc){
    echo '<div style="border:1px solid color-mix(in oklab, var(--chart-2) 40%, transparent);background:color-mix(in oklab, var(--chart-2) 10%, transparent);border-radius:0.75rem;padding:0.85rem 1rem;margin-bottom:1rem;font-size:0.8125rem;">';
    echo '<strong>Missing info or documents ('.(count($missing)+(!$avatarSrc?1:0)).'):</strong> ';
    $bits=[];
    if(!$avatarSrc) $bits[]='profile image';
    foreach($missing as $m) $bits[]=$m['label'];
    echo e(implode(', ', $bits));
    echo ' — <a href="'.base_url('onboarding/201-file.php').'">complete your 201 file</a>.';
    echo '</div>';
}
?>
<div style="display:grid;gap:1rem;max-width:52rem;">
<div class="card tab-notch tab-teal"><div class="card-content" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
<?php if($avatarSrc): ?><img src="<?= e($avatarSrc) ?>" alt="Profile image" style="width:3.5rem;height:3.5rem;border-radius:9999px;object-fit:cover;"><?php else: ?><div style="width:3.5rem;height:3.5rem;border-radius:9999px;background:var(--primary);color:var(--primary-foreground);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;"><?= e($initials) ?></div><?php endif; ?>
<div style="flex:1;min-width:12rem;"><div style="font-weight:700;font-size:1.1rem;"><?= e($emp['first_name'].' '.$emp['last_name']) ?></div><div style="font-size:0.8125rem;color:var(--muted-foreground);"><?= e($emp['employee_number'] ?? $emp['employee_code']) ?> • <?= e($emp['email']) ?> • <?= e($emp['role_name']) ?></div><div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e($emp['dept_name']??'-') ?> • <?= e($emp['pos_title']??'-') ?></div></div>
<form method="POST" enctype="multipart/form-data" style="display:flex;gap:0.5rem;align-items:center;"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="file" name="avatar" accept=".png,.jpg,.jpeg,.webp" class="input" style="max-width:12rem;" required><button name="upload_avatar" class="btn btn-outline btn-sm">Upload image</button></form>
</div></div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">201 file — <?= (int)$progress['done'] ?> of <?= (int)$progress['total'] ?> required items</div><div class="card-desc"><?= $emp['is_201_submitted'] ? 'Submitted' : 'Not yet submitted' ?></div></div>
<div class="card-content"><div style="display:flex;gap:0.4rem;flex-wrap:wrap;"><?php foreach($progress['items'] as $it): ?><span class="badge <?= $it['done']?'badge-green':'badge-brass' ?>"><?= $it['done']?'✓ ':'○ ' ?><?= e($it['label']) ?></span><?php endforeach; ?></div>
<div style="margin-top:0.6rem;"><a href="<?= base_url('onboarding/201-file.php') ?>" class="btn btn-outline btn-sm">Open 201 file</a></div></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Details</div></div><div class="card-content" style="display:grid;gap:0.5rem;font-size:0.875rem;">
<div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Employee Number</span><span style="font-family:var(--font-mono);"><?= e($emp['employee_number'] ?? $emp['employee_code']) ?></span></div>
<div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Hire Date</span><span><?= e(date('M d, Y', strtotime($emp['hire_date']))) ?></span></div>
<div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Status</span><span class="badge <?= badge_class($emp['status']) ?>"><?= e($emp['status']) ?></span></div>
<div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">DOB</span><span><?= $emp['date_of_birth']?e(date('M d, Y', strtotime($emp['date_of_birth']))):'-' ?></span></div>
</div></div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Edit Contact</div></div><div class="card-content">
<form method="POST" style="display:grid;gap:0.75rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;">Phone</label><input name="phone" class="input" value="<?= e($emp['phone']??'') ?>"></div>
<div><label style="font-size:0.8125rem;">Address</label><input name="address" class="input" value="<?= e($emp['address']??'') ?>"></div>
<button name="save_contact" class="btn btn-primary">Save</button>
</form>
<?php if($emp['emergency_contact']): ?><div style="margin-top:0.75rem;font-size:0.8125rem;"><span style="color:var(--muted-foreground);">Emergency Contact:</span> <?= e($emp['emergency_contact']) ?></div><?php endif; ?>
</div></div>
</div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Documents (<?= count($docList) ?>)</div><div class="card-desc">Your uploaded 201 documents</div></div><div class="card-content">
<?php if(!$docList): ?><p style="color:var(--muted-foreground);font-size:0.875rem;">No documents yet — <a href="<?= base_url('onboarding/201-file.php') ?>">upload them in your 201 file</a>.</p><?php else: ?><div style="display:grid;gap:0.5rem;"><?php foreach($docList as $d): ?><div style="display:flex;justify-content:space-between;align-items:center;font-size:0.875rem;"><span><?= e($d['name']) ?> <span class="badge badge-slate" style="margin-left:0.4rem;"><?= e($d['type']) ?></span></span><a href="<?= base_url('documents/download.php?id='.e($d['id'])) ?>" class="btn btn-outline btn-sm">Download</a></div><?php endforeach; ?></div><?php endif; ?>
</div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Educational attainment</div></div><div class="card-content" style="display:grid;gap:0.5rem;font-size:0.875rem;">
<?php if(!$eduList): ?><span style="color:var(--muted-foreground);">None added — <a href="<?= base_url('onboarding/201-file.php') ?>">add in your 201 file</a>.</span><?php else: foreach($eduList as $ed): ?><div><strong><?= e($ed['school']) ?></strong> <span class="badge badge-slate" style="margin-left:0.3rem;"><?= e($ed['level']) ?></span><div style="color:var(--muted-foreground);font-size:0.75rem;"><?= e($ed['course'] ?? '') ?><?= $ed['year_graduated'] ? ' • '.e($ed['year_graduated']) : '' ?></div></div><?php endforeach; endif; ?>
</div></div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Career experience</div></div><div class="card-content" style="display:grid;gap:0.5rem;font-size:0.875rem;">
<?php if(!$expList): ?><span style="color:var(--muted-foreground);">None added — <a href="<?= base_url('onboarding/201-file.php') ?>">add in your 201 file</a>.</span><?php else: foreach($expList as $ex): ?><div><strong><?= e($ex['position']) ?></strong> @ <?= e($ex['company']) ?><div style="color:var(--muted-foreground);font-size:0.75rem;"><?= $ex['start_date'] ? e(date('M Y', strtotime($ex['start_date']))) : '?' ?> – <?= $ex['end_date'] ? e(date('M Y', strtotime($ex['end_date']))) : 'Present' ?></div></div><?php endforeach; endif; ?>
</div></div>
</div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Change password</div></div><div class="card-content">
<form method="POST" style="display:grid;gap:0.75rem;max-width:24rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;">Current password</label><input name="current_password" type="password" required class="input" autocomplete="current-password"></div>
<div><label style="font-size:0.8125rem;">New password</label><input name="new_password" type="password" required class="input" autocomplete="new-password" placeholder="8+ characters, letter + number"></div>
<div><label style="font-size:0.8125rem;">Confirm new password</label><input name="confirm_password" type="password" required class="input" autocomplete="new-password"></div>
<button name="change_password" class="btn btn-primary">Change password</button>
</form>
</div></div>
</div>
<?php layout_footer(); ?>
