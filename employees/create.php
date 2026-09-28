<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$depts=$pdo->query("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$positions=$pdo->query("SELECT p.*, d.name as dept_name FROM positions p LEFT JOIN departments d ON d.id=p.department_id WHERE p.deleted_at IS NULL ORDER BY p.title")->fetchAll();
$roles=$pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verify_csrf()){ $error='Invalid token'; }
    else {
        $first=trim($_POST['first_name']??''); $last=trim($_POST['last_name']??''); $email=trim($_POST['email']??''); $password=$_POST['password']??'';
        $code=trim($_POST['employee_code']??''); $dept=$_POST['department_id']??null; if($dept==='')$dept=null; $pos=$_POST['position_id']??null; if($pos==='')$pos=null;
        $hire=$_POST['hire_date']??date('Y-m-d'); $status=$_POST['status']??'ACTIVE'; $roleId=$_POST['role_id']??'';
        $phone=trim($_POST['phone']??''); $address=trim($_POST['address']??''); $emergency=trim($_POST['emergency_contact']??''); $dob=$_POST['date_of_birth']??null; if($dob==='')$dob=null;
        if(!$first||!$last||!$email||!$password||!$code||!$roleId){ $error='All required fields must be filled'; }
        else {
            // check email/code unique
            $chk=$pdo->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$email]); if($chk->fetch()){ $error='Email already exists'; }
            else {
                $chk2=$pdo->prepare("SELECT id FROM employees WHERE employee_code=?"); $chk2->execute([$code]); if($chk2->fetch()){ $error='Employee code exists'; }
                else {
                    $uid=uuid(); $eid=uuid(); $epid=uuid();
                    $hash=password_hash($password, PASSWORD_BCRYPT);
                    $pdo->beginTransaction();
                    try{
                        $pdo->prepare("INSERT INTO users (id,email,password,role_id,email_verified) VALUES (?,?,?,?,NOW())")->execute([$uid,$email,$hash,$roleId]);
                        $pdo->prepare("INSERT INTO employees (id,user_id,employee_code,first_name,last_name,department_id,position_id,hire_date,status) VALUES (?,?,?,?,?,?,?,?,'ACTIVE')")->execute([$eid,$uid,$code,$first,$last, $dept,$pos,$hire]);
                        // status handled
                        $pdo->prepare("UPDATE employees SET status=? WHERE id=?")->execute([$status,$eid]);
                        $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,phone,address,emergency_contact,date_of_birth) VALUES (?,?,?,?,?,?)")->execute([$epid,$eid,$phone?:null,$address?:null,$emergency?:null,$dob]);
                        $pdo->commit();
                        audit_log('CREATE','employees',$eid,['code'=>$code]);
                        flash('success','Employee created');
                        redirect(base_url('employees/'));
                    }catch(Exception $e){ $pdo->rollBack(); $error='Failed: '.e($e->getMessage()); }
                }
            }
        }
    }
}
layout_header('Add Employee','Create a new employee record','teal');
if($error) echo '<div style="background:color-mix(in oklab, var(--destructive) 10%, transparent);color:var(--destructive);padding:0.75rem;border-radius:0.5rem;margin-bottom:1rem;">'.e($error).'</div>';
?>
<div class="card tab-notch tab-teal">
  <div class="card-content">
    <form method="POST" style="display:grid;gap:1rem;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">First Name *</label><input name="first_name" required class="input" value="<?= e($_POST['first_name']??'') ?>"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Last Name *</label><input name="last_name" required class="input" value="<?= e($_POST['last_name']??'') ?>"></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Email *</label><input name="email" type="email" required class="input" value="<?= e($_POST['email']??'') ?>"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Password *</label><input name="password" type="password" required class="input"></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Employee Code *</label><input name="employee_code" required class="input" placeholder="EMP009" value="<?= e($_POST['employee_code']??'') ?>"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Role *</label><select name="role_id" required class="input"><option value="">Select role</option><?php foreach($roles as $r): ?><option value="<?= e($r['id']) ?>" <?= (($_POST['role_id']??'')==$r['id']?'selected':'') ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Department</label><select name="department_id" class="input"><option value="">None</option><?php foreach($depts as $d): ?><option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Position</label><select name="position_id" class="input"><option value="">None</option><?php foreach($positions as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['title']) ?> (<?= e($p['dept_name']) ?>)</option><?php endforeach; ?></select></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Hire Date</label><input name="hire_date" type="date" class="input" value="<?= e($_POST['hire_date']??date('Y-m-d')) ?>"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Status</label><select name="status" class="input"><option>ACTIVE</option><option>ON_LEAVE</option><option>PROBATION</option><option>TERMINATED</option></select></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
        <div><label style="font-size:0.8125rem;font-weight:500;">Phone</label><input name="phone" class="input"></div>
        <div><label style="font-size:0.8125rem;font-weight:500;">Date of Birth</label><input name="date_of_birth" type="date" class="input"></div>
      </div>
      <div><label style="font-size:0.8125rem;font-weight:500;">Address</label><input name="address" class="input"></div>
      <div><label style="font-size:0.8125rem;font-weight:500;">Emergency Contact</label><input name="emergency_contact" class="input"></div>
      <div style="display:flex;gap:0.5rem;"><button class="btn btn-primary">Create Employee</button><a href="<?= base_url('employees/') ?>" class="btn btn-outline">Cancel</a></div>
    </form>
  </div>
</div>
<?php layout_footer(); ?>
