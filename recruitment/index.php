<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $act=$_POST['action']??'';
    if($act==='create'){ $title=trim($_POST['title']??''); $dept=$_POST['department_id']??''; $desc=trim($_POST['description']??''); $req=trim($_POST['requirements']??''); $status=$_POST['status']??'OPEN'; $openings=max(1,(int)($_POST['openings']??1)); if($title&&$dept){ try{ $pdo->prepare("INSERT INTO recruitment_jobs (id,title,department_id,description,requirements,status,openings) VALUES (?,?,?,?,?,?,?)")->execute([uuid(),$title,$dept,$desc?:'-',$req?:'-',$status,$openings]); audit_log('CREATE','recruitment_jobs',null,['title'=>$title]); flash('success','Job created'); }catch(Exception $e){ flash('error',$e->getMessage()); } } redirect(base_url('recruitment/')); }
    if($act==='update'){ $id=$_POST['id']??''; $title=trim($_POST['title']??''); $dept=$_POST['department_id']??''; $desc=trim($_POST['description']??''); $req=trim($_POST['requirements']??''); $status=$_POST['status']??'OPEN'; $openings=max(1,(int)($_POST['openings']??1)); if($id){ $pdo->prepare("UPDATE recruitment_jobs SET title=?,department_id=?,description=?,requirements=?,status=?,openings=? WHERE id=?")->execute([$title,$dept,$desc,$req,$status,$openings,$id]); audit_log('UPDATE','recruitment_jobs',$id,null); flash('success','Job updated'); } redirect(base_url('recruitment/')); }
}
if(isset($_GET['delete'])){ $pdo->prepare("DELETE FROM recruitment_jobs WHERE id=?")->execute([$_GET['delete']]); flash('success','Job deleted'); redirect(base_url('recruitment/')); }
$depts=$pdo->query("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$jobs=$pdo->query("SELECT j.*, d.name as dept_name, (SELECT COUNT(*) FROM applicants a WHERE a.job_id=j.id) as app_count, (SELECT COUNT(*) FROM applicants a WHERE a.job_id=j.id AND a.status='HIRED') as hired_count FROM recruitment_jobs j LEFT JOIN departments d ON d.id=j.department_id ORDER BY j.created_at DESC")->fetchAll();
layout_header();
?>
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
  <button onclick="document.getElementById('dlg-create').showModal()" class="btn btn-primary">New Job</button> <a href="<?= base_url('recruitment/applicants.php') ?>" class="btn btn-outline">Applicants</a>
</div>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Title</th><th>Department</th><th>Status</th><th>Applicants</th><th>Filled / Open</th><th>Posted</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($jobs as $j): ?>
<tr style="cursor:pointer;" onclick="window.location='<?= base_url('recruitment/applicants.php?job='.e($j['id'])) ?>'">
<td style="font-weight:500;"><?= e($j['title']) ?></td><td><?= e($j['dept_name']??'-') ?></td><td><span class="badge <?= badge_class($j['status']) ?>"><?= e($j['status']) ?></span></td><td><?= (int)$j['app_count'] ?></td><td style="font-size:0.8125rem;"><span class="badge badge-slate"><?= (int)$j['hired_count'] ?> filled</span> <span style="color:var(--muted-foreground);">/ <?= max(0,(int)($j['openings']??1)-(int)$j['hired_count']) ?> open of <?= (int)($j['openings']??1) ?></span></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e(date('M d, Y', strtotime($j['posted_at']))) ?></td>
<td onclick="event.stopPropagation()"><div style="display:flex;gap:0.25rem;">
<button onclick="openEdit('<?= e($j['id']) ?>','<?= e($j['title']) ?>','<?= e($j['department_id']) ?>','<?= e(str_replace("'", "\'", $j['description'])) ?>','<?= e(str_replace("'", "\'", $j['requirements'])) ?>','<?= e($j['status']) ?>',<?= (int)($j['openings']??1) ?>)" class="btn btn-outline btn-sm" style="height:1.5rem;">Edit</button>
<a href="?delete=<?= e($j['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);">Delete</a>
</div></td>
</tr>
<?php endforeach; ?>
<?php if(empty($jobs)): ?><tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No jobs</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>

<dialog id="dlg-create" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:32rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">New Job</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
<div><label style="font-size:0.8125rem;font-weight:500;">Title *</label><input name="title" required class="input"></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Department *</label><select name="department_id" required class="input"><option value="">Select</option><?php foreach($depts as $d): ?><option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Description</label><textarea name="description" class="input" rows="3"></textarea></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Requirements</label><textarea name="requirements" class="input" rows="3"></textarea></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Status</label><select name="status" class="input"><option>OPEN</option><option>DRAFT</option><option>CLOSED</option></select></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Openings (headcount)</label><input name="openings" type="number" min="1" value="1" class="input"></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Create</button></div>
</form>
</dialog>
<dialog id="dlg-edit" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:32rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Edit Job</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit-id">
<div><label style="font-size:0.8125rem;font-weight:500;">Title *</label><input name="title" id="edit-title" required class="input"></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Department *</label><select name="department_id" id="edit-dept" class="input"><?php foreach($depts as $d): ?><option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Description</label><textarea name="description" id="edit-desc" class="input" rows="3"></textarea></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Requirements</label><textarea name="requirements" id="edit-req" class="input" rows="3"></textarea></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Status</label><select name="status" id="edit-status" class="input"><option>OPEN</option><option>DRAFT</option><option>CLOSED</option></select></div>
<div><label style="font-size:0.8125rem;font-weight:500;">Openings (headcount)</label><input name="openings" id="edit-openings" type="number" min="1" value="1" class="input"></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Save</button></div>
</form>
</dialog>
<script>function openEdit(id,title,dept,desc,req,status,openings){ document.getElementById('edit-id').value=id; document.getElementById('edit-title').value=title; document.getElementById('edit-dept').value=dept; document.getElementById('edit-desc').value=desc; document.getElementById('edit-req').value=req; document.getElementById('edit-status').value=status; document.getElementById('edit-openings').value=openings||1; document.getElementById('dlg-edit').showModal(); }</script>
<?php layout_footer(); ?>
