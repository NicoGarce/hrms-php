<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$error='';
// handle create/update/delete
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $act=$_POST['action']??'';
    if($act==='create'){
        $name=trim($_POST['name']??''); $code=trim($_POST['code']??''); $desc=trim($_POST['description']??'');
        if($name && $code){ try{ $pdo->prepare("INSERT INTO departments (id,name,code,description) VALUES (?,?,?,?)")->execute([uuid(),$name,$code,$desc?:null]); flash('success','Department created'); }catch(Exception $e){ $error=$e->getMessage(); } }
        redirect(base_url('departments/'));
    }
    if($act==='update'){
        $id=$_POST['id']??''; $name=trim($_POST['name']??''); $code=trim($_POST['code']??''); $desc=trim($_POST['description']??'');
        if($id && $name && $code){ $pdo->prepare("UPDATE departments SET name=?,code=?,description=? WHERE id=?")->execute([$name,$code,$desc?:null,$id]); flash('success','Department updated'); }
        redirect(base_url('departments/'));
    }
}
if(isset($_GET['delete'])){ $id=$_GET['delete']; $pdo->prepare("UPDATE departments SET deleted_at=NOW() WHERE id=?")->execute([$id]); flash('success','Department deleted'); redirect(base_url('departments/')); }
$depts=$pdo->query("SELECT d.*, (SELECT COUNT(*) FROM employees e WHERE e.department_id=d.id AND e.deleted_at IS NULL) as emp_count, (SELECT COUNT(*) FROM positions p WHERE p.department_id=d.id AND p.deleted_at IS NULL) as pos_count FROM departments d WHERE d.deleted_at IS NULL ORDER BY d.created_at DESC")->fetchAll();
layout_header('Departments','Organize your company structure','brass','<button onclick="document.getElementById(\'dlg-create\').showModal()" class="btn btn-primary"><i data-lucide="plus" style="width:0.875rem;height:0.875rem;"></i> New Department</button>');
if($error) echo '<div style="background:color-mix(in oklab, var(--destructive) 10%, transparent);color:var(--destructive);padding:0.75rem;border-radius:0.5rem;margin-bottom:1rem;">'.e($error).'</div>';
?>
<div class="card tab-notch tab-brass"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Name</th><th>Code</th><th>Description</th><th>Employees</th><th>Positions</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($depts as $d): ?>
<tr>
<td style="font-weight:500;display:flex;align-items:center;gap:0.5rem;"><span style="width:1.75rem;height:1.75rem;border-radius:0.5rem;background:color-mix(in oklab, var(--chart-2) 15%, transparent);display:flex;align-items:center;justify-content:center;"><i data-lucide="building-2" style="width:0.875rem;height:0.875rem;color:var(--chart-2);"></i></span><?= e($d['name']) ?></td>
<td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($d['code']) ?></td>
<td style="font-size:0.8125rem;color:var(--muted-foreground);max-width:18rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['description'] ?? '-') ?></td>
<td><span class="badge badge-slate"><?= (int)$d['emp_count'] ?></span></td>
<td><span class="badge badge-slate"><?= (int)$d['pos_count'] ?></span></td>
<td><div style="display:flex;gap:0.25rem;">
<button onclick="openEdit('<?= e($d['id']) ?>','<?= e($d['name']) ?>','<?= e($d['code']) ?>','<?= e($d['description']??'') ?>')" class="btn btn-outline btn-sm" style="height:1.5rem;">Edit</button>
<a href="?delete=<?= e($d['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);">Delete</a>
</div></td>
</tr>
<?php endforeach; ?>
<?php if(empty($depts)): ?><tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No departments</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>

<dialog id="dlg-create" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);box-shadow:0 16px 48px oklch(0 0 0 / 0.2);">
  <form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
    <h3 style="font-weight:600;">New Department</h3>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
    <div><label style="font-size:0.8125rem;font-weight:500;">Name *</label><input name="name" required class="input"></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Code *</label><input name="code" required class="input" placeholder="ENG"></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Description</label><textarea name="description" class="input" rows="3"></textarea></div>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Create</button></div>
  </form>
</dialog>
<dialog id="dlg-edit" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);box-shadow:0 16px 48px oklch(0 0 0 / 0.2);">
  <form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
    <h3 style="font-weight:600;">Edit Department</h3>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit-id">
    <div><label style="font-size:0.8125rem;font-weight:500;">Name *</label><input name="name" id="edit-name" required class="input"></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Code *</label><input name="code" id="edit-code" required class="input"></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Description</label><textarea name="description" id="edit-desc" class="input" rows="3"></textarea></div>
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Save</button></div>
  </form>
</dialog>
<script>function openEdit(id,name,code,desc){ document.getElementById('edit-id').value=id; document.getElementById('edit-name').value=name; document.getElementById('edit-code').value=code; document.getElementById('edit-desc').value=desc; document.getElementById('dlg-edit').showModal(); }</script>
<?php layout_footer(); ?>
