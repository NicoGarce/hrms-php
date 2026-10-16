<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$depts=$pdo->query("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$positions=$pdo->query("SELECT p.*, d.name as dept_name FROM positions p LEFT JOIN departments d ON d.id=p.department_id WHERE p.deleted_at IS NULL ORDER BY p.title")->fetchAll();
$previewNumber = next_employee_number();
$error='';
// Prefill from recruitment conversion
$prefFirst = $_GET['first'] ?? ''; $prefLast = $_GET['last'] ?? ''; $prefEmail = $_GET['email'] ?? '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verify_csrf()){ $error='Invalid token'; }
    else {
        $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??''); $email=trim($_POST['email']??'');
        $dept=$_POST['department_id']??null; if($dept==='')$dept=null; $pos=$_POST['position_id']??null; if($pos==='')$pos=null;
        $hire=$_POST['hire_date']??date('Y-m-d'); $status=$_POST['status']??'ACTIVE';
        $phone=trim($_POST['phone']??''); $address=trim($_POST['address']??''); $emergency=trim($_POST['emergency_contact']??''); $dob=$_POST['date_of_birth']??null; if($dob==='')$dob=null;
        if(!$first||!$last||!$email){ $error='First name, last name and email are required'; }
        elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){ $error='Invalid email address'; }
        else {
            $chk=$pdo->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$email]); if($chk->fetch()){ $error='Email already exists'; }
            else {
                $roleId=$pdo->query("SELECT id FROM roles WHERE name='EMPLOYEE' LIMIT 1")->fetchColumn();
                if(!$roleId){ $error='EMPLOYEE role missing'; }
                else {
                    // Auto-generate Employee Number (retry on rare collision).
                    $empNo=''; $tries=0;
                    while($tries<5){
                        $candidate = next_employee_number();
                        $c=$pdo->prepare("SELECT id FROM users WHERE employee_number=?"); $c->execute([$candidate]);
                        if(!$c->fetch()){ $empNo=$candidate; break; }
                        $tries++;
                    }
                    if($empNo===''){ $error='Could not generate employee number, try again'; }
                    else {
                        $uid=uuid(); $eid=uuid(); $epid=uuid();
                        $hash=password_hash($empNo, PASSWORD_BCRYPT); // default password = employee number
                        $pdo->beginTransaction();
                        try{
                            $pdo->prepare("INSERT INTO users (id,email,employee_number,password,role_id,account_state,email_verified) VALUES (?,?,?,?,?,'FIRST_LOGIN',NULL)")->execute([$uid,$email,$empNo,$hash,$roleId]);
                            $pdo->prepare("INSERT INTO employees (id,user_id,employee_code,first_name,last_name,department_id,position_id,hire_date,status) VALUES (?,?,?,?,?,?,?,?,'ACTIVE')")->execute([$eid,$uid,$empNo,$first,$last,$dept,$pos,$hire]);
                            if($status && $status!=='ACTIVE'){
                                $pdo->prepare("UPDATE employees SET status=? WHERE id=?")->execute([$status,$eid]);
                                if(in_array($status,['TERMINATED','RESIGNED'],true)){
                                    $pdo->prepare("UPDATE users SET account_state='DEACTIVATED' WHERE id=?")->execute([$uid]);
                                }
                            }
                            $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,phone,address,emergency_contact,date_of_birth,onboarding_deadline) VALUES (?,?,?,?,?,?,DATE_ADD(CURDATE(), INTERVAL 14 DAY))")->execute([$epid,$eid,$phone?:null,$address?:null,$emergency?:null,$dob]);
                            $pdo->commit();
                            audit_log('CREATE','employees',$eid,['employee_number'=>$empNo,'email'=>$email]);
                            flash('success','Employee created with Employee Number '.$empNo.'. They log in with this number (default password is the same number) and will set their own password.');
                            redirect(base_url('employees/'));
                        }catch(Exception $e){ $pdo->rollBack(); $error='Failed: '.$e->getMessage(); }
                    }
                }
            }
        }
    }
}
layout_header('Add Employee','Employee Number is auto-generated. Account starts in FIRST_LOGIN — HR never handles credentials.','teal');
if($error) echo '<div style="background:color-mix(in oklab, var(--destructive) 10%, transparent);color:var(--destructive);padding:0.75rem;border-radius:0.5rem;margin-bottom:1rem;">'.e($error).'</div>';
?>
<div class="card tab-notch tab-teal">
  <div class="card-content">
    <form method="POST" style="display:grid;gap:1rem;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">First Name *</label><input name="first_name" required class="input" value="<?= e($_POST['first_name']??$prefFirst) ?>"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Last Name *</label><input name="last_name" required class="input" value="<?= e($_POST['last_name']??$prefLast) ?>"></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Email (contact/notifications only) *</label><input name="email" type="email" required class="input" value="<?= e($_POST['email']??$prefEmail) ?>"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Employee Number (auto-generated)</label><input class="input" value="<?= e($previewNumber) ?>" disabled><div style="font-size:0.72rem;color:var(--muted-foreground);margin-top:0.2rem;">Final number is assigned on save. Not editable afterwards.</div></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Department</label><select name="department_id" class="input"><option value="">None</option><?php foreach($depts as $d): ?><option value="<?= e($d['id']) ?>" <?= (($_POST['department_id']??'')==$d['id']?'selected':'') ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Position</label><select name="position_id" class="input"><option value="">None</option><?php foreach($positions as $p): ?><option value="<?= e($p['id']) ?>" <?= (($_POST['position_id']??'')==$p['id']?'selected':'') ?>><?= e($p['title']) ?> (<?= e($p['dept_name']) ?>)</option><?php endforeach; ?></select></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Hire Date</label><input name="hire_date" type="date" class="input" value="<?= e($_POST['hire_date']??date('Y-m-d')) ?>"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Status</label><select name="status" class="input"><option>ACTIVE</option><option>ON_LEAVE</option><option>PROBATION</option><option>TERMINATED</option><option>RESIGNED</option></select></div>
      </div>
      <div style="font-size:0.8125rem;font-weight:600;">Initial 201 info (HR enters what is known; employee completes the rest)</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Phone</label><input name="phone" class="input" value="<?= e($_POST['phone']??'') ?>"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Date of Birth</label><input name="date_of_birth" type="date" class="input" value="<?= e($_POST['date_of_birth']??'') ?>"></div>
      </div>
      <div><label style="font-size:0.8125rem;font-weight:500;">Address</label><input name="address" class="input" value="<?= e($_POST['address']??'') ?>"></div>
      <div><label style="font-size:0.8125rem;font-weight:500;">Emergency Contact</label><input name="emergency_contact" class="input" value="<?= e($_POST['emergency_contact']??'') ?>"></div>
      <div style="font-size:0.75rem;color:var(--muted-foreground);">Role defaults to Employee. To grant HR/Head access, a Super Admin changes the role in User Management. 201-file deadline is 14 days after creation (reminder only).</div>
      <div style="display:flex;gap:0.5rem;"><button class="btn btn-primary">Create Employee</button><a href="<?= base_url('employees/') ?>" class="btn btn-outline">Cancel</a></div>
    </form>
  </div>
</div>
<?php layout_footer(); ?>
