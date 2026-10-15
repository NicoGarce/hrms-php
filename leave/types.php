<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $act=$_POST['action']??'';
    if($act==='create'){ $name=trim($_POST['name']??''); $days=(int)($_POST['days_allowed']??0); $cf=isset($_POST['carry_forward'])?1:0; if($name){ try{ $pdo->prepare("INSERT INTO leave_types (id,name,days_allowed,carry_forward) VALUES (?,?,?,?)")->execute([uuid(),$name,$days,$cf]); flash('success','Leave type created'); }catch(Exception $e){ flash('error',$e->getMessage()); } } redirect(base_url('leave/types.php')); }
    if($act==='update'){ $id=$_POST['id']??''; $name=trim($_POST['name']??''); $days=(int)($_POST['days_allowed']??0); $cf=isset($_POST['carry_forward'])?1:0; if($id&&$name){ $pdo->prepare("UPDATE leave_types SET name=?,days_allowed=?,carry_forward=? WHERE id=?")->execute([$name,$days,$cf,$id]); flash('success','Updated'); } redirect(base_url('leave/types.php')); }
}
if(isset($_GET['delete'])){ $pdo->prepare("DELETE FROM leave_types WHERE id=?")->execute([$_GET['delete']]); flash('success','Deleted'); redirect(base_url('leave/types.php')); }
$types=$pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();
layout_header();
?>
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
  <button onclick="document.getElementById('dlg-create').showModal()" class="btn btn-primary">New Type</button> <a href="<?= base_url('leave/') ?>" class="btn btn-outline">Back</a>
</div>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Name</th><th>Days Allowed</th><th>Carry Forward</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($types as $t): ?><tr><td style="font-weight:500;"><?= e($t['name']) ?></td><td><?= (int)$t['days_allowed'] ?></td><td><?= $t['carry_forward']?'Yes':'No' ?></td><td><div style="display:flex;gap:0.25rem;"><button onclick="openEdit('<?= e($t['id']) ?>','<?= e($t['name']) ?>','<?= (int)$t['days_allowed'] ?>',<?= $t['carry_forward']?'true':'false' ?>)" class="btn btn-outline btn-sm" style="height:1.5rem;">Edit</button><a href="?delete=<?= e($t['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);">Delete</a></div></td></tr><?php endforeach; ?>
<?php if(empty($types)): ?><tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No leave types</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>
<dialog id="dlg-create" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:24rem;width:calc(100% - 2rem);"><form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;"><h3 style="font-weight:600;">New Leave Type</h3><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><div><label style="font-size:0.8125rem;">Name *</label><input name="name" required class="input"></div><div><label style="font-size:0.8125rem;">Days Allowed</label><input name="days_allowed" type="number" min="0" class="input" value="21"></div><label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;"><input type="checkbox" name="carry_forward"> Carry Forward</label><div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Create</button></div></form></dialog>
<dialog id="dlg-edit" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:24rem;width:calc(100% - 2rem);"><form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;"><h3 style="font-weight:600;">Edit Leave Type</h3><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit-id"><div><label style="font-size:0.8125rem;">Name *</label><input name="name" id="edit-name" required class="input"></div><div><label style="font-size:0.8125rem;">Days Allowed</label><input name="days_allowed" id="edit-days" type="number" min="0" class="input"></div><label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;"><input type="checkbox" name="carry_forward" id="edit-cf"> Carry Forward</label><div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Save</button></div></form></dialog>
<script>function openEdit(id,name,days,cf){ document.getElementById('edit-id').value=id; document.getElementById('edit-name').value=name; document.getElementById('edit-days').value=days; document.getElementById('edit-cf').checked=cf; document.getElementById('dlg-edit').showModal(); }</script>
<?php layout_footer(); ?>
