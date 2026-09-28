<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $act=$_POST['action']??'';
    if($act==='create'){
        $title=trim($_POST['title']??''); $dept=$_POST['department_id']??''; $level=$_POST['level']??'MID'; $desc=trim($_POST['description']??'');
        if($title && $dept){ try{ $pdo->prepare("INSERT INTO positions (id,title,department_id,level,description) VALUES (?,?,?,?,?)")->execute([uuid(),$title,$dept,$level,$desc?:null]); flash('success','Position created'); }catch(Exception $e){ $error=$e->getMessage(); } }
        redirect(base_url('positions/'));
    }
    if($act==='update'){
        $id=$_POST['id']??''; $title=trim($_POST['title']??''); $dept=$_POST['department_id']??''; $level=$_POST['level']??'MID'; $desc=trim($_POST['description']??'');
        if($id && $title){ $pdo->prepare("UPDATE positions SET title=?,department_id=?,level=?,description=? WHERE id=?")->execute([$title,$dept,$level,$desc?:null,$id]); flash('success','Position updated'); }
        redirect(base_url('positions/'));
    }
}
if(isset($_GET['delete'])){ $pdo->prepare("UPDATE positions SET deleted_at=NOW() WHERE id=?")->execute([$_GET['delete']]); flash('success','Position deleted'); redirect(base_url('positions/')); }
$depts=$pdo->query("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$positions=$pdo->query("SELECT p.*, d.name as dept_name, (SELECT COUNT(*) FROM employees e WHERE e.position_id=p.id AND e.deleted_at IS NULL) as emp_count FROM positions p LEFT JOIN departments d ON d.id=p.department_id WHERE p.deleted_at IS NULL ORDER BY p.created_at DESC")->fetchAll();
layout_header('Positions','Define roles within departments','brass','<button onclick="document.getElementById(\'dlg-create\').showModal()" class="btn btn-primary"><i data-lucide="plus" style="width:0.875rem;height:0.875rem;"></i> New Position</button>');
if($error) echo '<div style="background:color-mix(in oklab, var(--destructive) 10%, transparent);color:var(--destructive);padding:0.75rem;border-radius:0.5rem;margin-bottom:1rem;">'.e($error).'</div>';
?>
<div class="card tab-notch tab-brass"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Title</th><th>Department</th><th>Level</th><th>Employees</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($positions as $p): ?>
<tr>
<td style="font-weight:500;display:flex;align-items:center;gap:0.5rem;"><span style="width:1.75rem;height:1.75rem;border-radius:0.5rem;background:color-mix(in oklab, var(--chart-2) 15%, transparent);display:flex;align-items:center;justify-content:center;"><i data-lucide="briefcase" style="width:0.875rem;height:0.875rem;color:var(--chart-2);"></i></span><?= e($p['title']) ?></td>
<td><?= e($p['dept_name'] ?? '-') ?></td>
<td><span class="badge badge-slate"><?= e($p['level']) ?></span></td>
<td><span class="badge badge-slate"><?= (int)$p['emp_count'] ?></span></td>
<td><div style="display:flex;gap:0.25rem;">
<button onclick="openEdit('<?= e($p['id']) ?>','<?= e($p['title']) ?>','<?= e($p['department_id']) ?>','<?= e($p['level']) ?>','<?= e($p['description']??'') ?>')" class="btn btn-outline btn-sm" style="height:1.5rem;">Edit</button>
<a href="?delete=<?= e($p['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);">Delete</a>
</div></td>
</tr>
<?php endforeach; ?>
<?php if(empty($positions)): ?><tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No positions</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>

<dialog id="dlg-create" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
  <form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
    <h3 style="font-weight:600;">New Position</h3>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
    <div><label style="font-size:0.8125rem;font-weight:500;">Title *</label><input name="title" required class="input"></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Department *</label><select name="department_id" required class="input"><option value="">Select</option><?php foreach($depts as $d): ?><option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Level</label><select name="level" class="input"><option>JUNIOR</option><option>MID</option><option>SENIOR</option><option>LEAD</option><option>EXECUTIVE</option></select></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Description</label><textarea name="description" class="input" rows="2"></textarea></div>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Create</button></div>
  </form>
</dialog>
<dialog id="dlg-edit" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
  <form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
    <h3 style="font-weight:600;">Edit Position</h3>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit-id">
    <div><label style="font-size:0.8125rem;font-weight:500;">Title *</label><input name="title" id="edit-title" required class="input"></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Department *</label><select name="department_id" id="edit-dept" class="input"><?php foreach($depts as $d): ?><option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Level</label><select name="level" id="edit-level" class="input"><option>JUNIOR</option><option>MID</option><option>SENIOR</option><option>LEAD</option><option>EXECUTIVE</option></select></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Description</label><textarea name="description" id="edit-desc" class="input" rows="2"></textarea></div>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Save</button></div>
  </form>
</dialog>
<script>function openEdit(id,title,dept,level,desc){ document.getElementById('edit-id').value=id; document.getElementById('edit-title').value=title; document.getElementById('edit-dept').value=dept; document.getElementById('edit-level').value=level; document.getElementById('edit-desc').value=desc; document.getElementById('dlg-edit').showModal(); }</script>
<?php layout_footer(); ?>
