<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$id=$_GET['id']??''; if(!$id) redirect(base_url('employees/'));
$stmt=$pdo->prepare("SELECT e.*, u.email, u.employee_number, u.role_id, u.account_state, ep.phone, ep.address, ep.emergency_contact, ep.date_of_birth FROM employees e JOIN users u ON u.id=e.user_id LEFT JOIN employee_profiles ep ON ep.employee_id=e.id WHERE e.id=? AND e.deleted_at IS NULL");
$stmt->execute([$id]); $emp=$stmt->fetch(); if(!$emp){ flash('error','Not found'); redirect(base_url('employees/')); }
$depts=$pdo->query("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$positions=$pdo->query("SELECT p.*, d.name as dept_name FROM positions p LEFT JOIN departments d ON d.id=p.department_id WHERE p.deleted_at IS NULL ORDER BY p.title")->fetchAll();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verify_csrf()) $error='Invalid token';
    else{
        $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??''); $email=trim($_POST['email']??'');
        $dept=$_POST['department_id']??null; if($dept==='')$dept=null; $pos=$_POST['position_id']??null; if($pos==='')$pos=null;
        $hire=$_POST['hire_date']??$emp['hire_date']; $status=$_POST['status']??'ACTIVE';
        $phone=trim($_POST['phone']??''); $address=trim($_POST['address']??''); $emergency=trim($_POST['emergency_contact']??''); $dob=$_POST['date_of_birth']??null; if($dob==='')$dob=null;
        if(!$first||!$last||!$email){ $error='Required fields missing'; }
        elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){ $error='Invalid email address'; }
        else {
            try{
                $pdo->beginTransaction();
                if($email!==$emp['email']){
                    $chk=$pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?"); $chk->execute([$email,$emp['user_id']]); if($chk->fetch()) throw new Exception('Email already taken');
                }
                // Employee Number is immutable — never updated here.
                $pdo->prepare("UPDATE employees SET first_name=?,last_name=?,department_id=?,position_id=?,hire_date=?,status=? WHERE id=?")->execute([$first,$last,$dept,$pos,$hire,$status,$id]);
                $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$email,$emp['user_id']]);
                // Resigned / terminated automatically deactivates the account.
                if(in_array($status,['TERMINATED','RESIGNED'],true) && $emp['account_state']!=='DEACTIVATED'){
                    $pdo->prepare("UPDATE users SET account_state='DEACTIVATED' WHERE id=?")->execute([$emp['user_id']]);
                }
                $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,phone,address,emergency_contact,date_of_birth) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE phone=VALUES(phone), address=VALUES(address), emergency_contact=VALUES(emergency_contact), date_of_birth=VALUES(date_of_birth)")->execute([uuid(),$id,$phone?:null,$address?:null,$emergency?:null,$dob]);
                $pdo->commit();
                audit_log('UPDATE','employees',$id,['status'=>$status]);
                flash('success','Employee updated' . (in_array($status,['TERMINATED','RESIGNED'],true) ? ' and account deactivated' : ''));
                redirect(base_url('employees/view.php?id='.$id));
            }catch(Exception $e){ $pdo->rollBack(); $error=$e->getMessage(); }
        }
    }
}
layout_header('Edit Employee', e($emp['first_name'].' '.$emp['last_name']).' — '.$emp['employee_number'], 'teal');
if($error) echo '<div style="background:color-mix(in oklab, var(--destructive) 10%, transparent);color:var(--destructive);padding:0.75rem;border-radius:0.5rem;margin-bottom:1rem;">'.e($error).'</div>';
?>
<div class="card tab-notch tab-teal"><div class="card-content">
<form method="POST" style="display:grid;gap:1rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
  <div><label style="font-size:0.8125rem;font-weight:500;">First Name *</label><input name="first_name" required class="input" value="<?= e($_POST['first_name']??$emp['first_name']) ?>"></div>
  <div><label style="font-size:0.8125rem;font-weight:500;">Last Name *</label><input name="last_name" required class="input" value="<?= e($_POST['last_name']??$emp['last_name']) ?>"></div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
  <div><label style="font-size:0.8125rem;font-weight:500;">Email (contact only) *</label><input name="email" type="email" required class="input" value="<?= e($_POST['email']??$emp['email']) ?>"></div>
  <div><label style="font-size:0.8125rem;font-weight:500;">Employee Number (not editable)</label><input class="input" value="<?= e($emp['employee_number']) ?>" disabled></div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
  <div><label style="font-size:0.8125rem;font-weight:500;">Department</label><select name="department_id" class="input"><option value="">None</option><?php foreach($depts as $d): ?><option value="<?= e($d['id']) ?>" <?= ($emp['department_id']==$d['id']?'selected':'') ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
  <div><label style="font-size:0.8125rem;font-weight:500;">Position</label><select name="position_id" class="input"><option value="">None</option><?php foreach($positions as $p): ?><option value="<?= e($p['id']) ?>" <?= ($emp['position_id']==$p['id']?'selected':'') ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
  <div><label style="font-size:0.8125rem;font-weight:500;">Hire Date</label><input name="hire_date" type="date" class="input" value="<?= e($_POST['hire_date']??substr($emp['hire_date'],0,10)) ?>"></div>
  <div><label style="font-size:0.8125rem;font-weight:500;">Status</label><select name="status" class="input"><?php foreach(['ACTIVE','ON_LEAVE','PROBATION','TERMINATED','RESIGNED'] as $s): ?><option <?= $emp['status']==$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
  <div style="font-size:0.72rem;color:var(--muted-foreground);margin-top:0.2rem;">TERMINATED / RESIGNED deactivates the login automatically.</div></div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
  <div><label style="font-size:0.8125rem;font-weight:500;">Phone</label><input name="phone" class="input" value="<?= e($_POST['phone']??$emp['phone']??'') ?>"></div>
  <div><label style="font-size:0.8125rem;font-weight:500;">Date of Birth</label><input name="date_of_birth" type="date" class="input" value="<?= e($_POST['date_of_birth']??($emp['date_of_birth']?substr($emp['date_of_birth'],0,10):'')) ?>"></div>
</div>
<div><label style="font-size:0.8125rem;font-weight:500;">Address</label><input name="address" class="input" value="<?= e($_POST['address']??$emp['address']??'') ?>"></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Emergency Contact</label><input name="emergency_contact" class="input" value="<?= e($_POST['emergency_contact']??$emp['emergency_contact']??'') ?>"></div>
<div style="font-size:0.75rem;color:var(--muted-foreground);">Roles and credentials are managed in User Management (Super Admin only).</div>
<div style="display:flex;gap:0.5rem;"><button class="btn btn-primary">Save Changes</button><a href="<?= base_url('employees/view.php?id='.e($id)) ?>" class="btn btn-outline">Cancel</a></div>
</form>
</div></div>
<?php layout_footer(); ?>
