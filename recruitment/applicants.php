<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$jobFilter=$_GET['job']??''; $statusFilter=$_GET['status']??'ALL';
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    if(isset($_POST['update_status'])){ $id=$_POST['id']??''; $st=$_POST['status']??''; if($id&&$st){ $pdo->prepare("UPDATE applicants SET status=? WHERE id=?")->execute([$st,$id]); flash('success','Status updated'); } redirect(base_url('recruitment/applicants.php?job='.e($jobFilter)));
    }
    if(isset($_POST['create_applicant'])){ $job=$_POST['job_id']??$jobFilter; $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??''); $email=trim($_POST['email']??''); $phone=trim($_POST['phone']??''); if($job&&$fn&&$ln&&$email){ $pdo->prepare("INSERT INTO applicants (id,job_id,first_name,last_name,email,phone,status) VALUES (?,?,?,?,?,?,?)")->execute([uuid(),$job,$fn,$ln,$email,$phone?:null,'APPLIED']); flash('success','Applicant added'); } redirect(base_url('recruitment/applicants.php?job='.e($job)));
    }
}
$jobs=$pdo->query("SELECT * FROM recruitment_jobs ORDER BY title")->fetchAll();
$sql="SELECT a.*, j.title as job_title FROM applicants a LEFT JOIN recruitment_jobs j ON j.id=a.job_id WHERE 1=1"; $params=[];
if($jobFilter){ $sql.=" AND a.job_id=?"; $params[]=$jobFilter; }
if($statusFilter!=='ALL'){ $sql.=" AND a.status=?"; $params[]=$statusFilter; }
$sql.=" ORDER BY a.applied_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $apps=$stmt->fetchAll();
layout_header('Applicants','Track candidates', 'teal', '<button onclick="document.getElementById(\'dlg-add\').showModal()" class="btn btn-primary">Add Applicant</button> <a href="'.base_url('recruitment/').'" class="btn btn-outline">Back to Jobs</a>');
?>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center;">
  <form method="GET" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
    <select name="job" class="input" style="width:14rem;" onchange="this.form.submit()"><option value="">All Jobs</option><?php foreach($jobs as $j): ?><option value="<?= e($j['id']) ?>" <?= $jobFilter==$j['id']?'selected':'' ?>><?= e($j['title']) ?></option><?php endforeach; ?></select>
    <select name="status" class="input" style="width:10rem;" onchange="this.form.submit()"><option value="ALL">All Status</option><?php foreach(['APPLIED','SCREENING','INTERVIEW','OFFER','HIRED','REJECTED'] as $s): ?><option <?= $statusFilter==$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
  </form>
</div>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Name</th><th>Job</th><th>Email</th><th>Phone</th><th>Status</th><th>Applied</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($apps as $a): ?>
<tr>
<td style="font-weight:500;"><?= e($a['first_name'].' '.$a['last_name']) ?></td><td><?= e($a['job_title']??'-') ?></td><td style="font-size:0.8125rem;"><?= e($a['email']) ?></td><td><?= e($a['phone']??'-') ?></td>
<td><span class="badge <?= badge_class($a['status']) ?>"><?= e($a['status']) ?></span></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e(date('M d, Y', strtotime($a['applied_at']))) ?></td>
<td><form method="POST" style="display:flex;gap:0.25rem;align-items:center;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($a['id']) ?>">
<select name="status" class="input" style="height:1.6rem;font-size:0.75rem;width:7rem;"><?php foreach(['APPLIED','SCREENING','INTERVIEW','OFFER','HIRED','REJECTED'] as $s): ?><option <?= $a['status']==$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
<button name="update_status" class="btn btn-outline btn-sm" style="height:1.6rem;">Update</button>
</form>
<?php if($a['status']==='HIRED'): ?><a href="<?= base_url('employees/create.php?first='.e($a['first_name']).'&last='.e($a['last_name']).'&email='.e($a['email'])) ?>" class="btn btn-primary btn-sm" style="height:1.6rem;margin-top:0.25rem;">Convert to Employee</a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($apps)): ?><tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No applicants</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>

<dialog id="dlg-add" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Add Applicant</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;font-weight:500;">Job *</label><select name="job_id" required class="input"><option value="">Select</option><?php foreach($jobs as $j): ?><option value="<?= e($j['id']) ?>" <?= $jobFilter==$j['id']?'selected':'' ?>><?= e($j['title']) ?></option><?php endforeach; ?></select></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;"><div><label style="font-size:0.8125rem;">First Name *</label><input name="first_name" required class="input"></div><div><label style="font-size:0.8125rem;">Last Name *</label><input name="last_name" required class="input"></div></div>
<div><label style="font-size:0.8125rem;">Email *</label><input name="email" type="email" required class="input"></div>
<div><label style="font-size:0.8125rem;">Phone</label><input name="phone" class="input"></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="create_applicant" class="btn btn-primary">Add</button></div>
</form>
</dialog>
<?php layout_footer(); ?>
