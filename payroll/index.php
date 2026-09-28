<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $act=$_POST['action']??'';
    if($act==='create'){ $emp=$_POST['employee_id']??''; $mon=(int)($_POST['month']??date('n')); $yr=(int)($_POST['year']??date('Y')); $base=(float)($_POST['base_salary']??0); $gross=(float)($_POST['gross_salary']??0); $net=(float)($_POST['net_salary']??0); $st=$_POST['status']??'DRAFT'; if($emp){ try{ $pdo->prepare("INSERT INTO payroll (id,employee_id,month,year,base_salary,gross_salary,net_salary,status) VALUES (?,?,?,?,?,?,?,?)")->execute([uuid(),$emp,$mon,$yr,$base,$gross,$net,$st]); flash('success','Payroll created'); }catch(Exception $e){ flash('error',$e->getMessage()); } } redirect(base_url('payroll/')); }
    if($act==='update'){ $id=$_POST['id']??''; $mon=(int)($_POST['month']??1); $yr=(int)($_POST['year']??2024); $base=(float)($_POST['base_salary']??0); $gross=(float)($_POST['gross_salary']??0); $net=(float)($_POST['net_salary']??0); $st=$_POST['status']??'DRAFT'; if($id){ $pdo->prepare("UPDATE payroll SET month=?,year=?,base_salary=?,gross_salary=?,net_salary=?,status=? WHERE id=?")->execute([$mon,$yr,$base,$gross,$net,$st,$id]); flash('success','Payroll updated'); } redirect(base_url('payroll/')); }
}
if(isset($_GET['delete'])){ $pdo->prepare("DELETE FROM payroll WHERE id=?")->execute([$_GET['delete']]); flash('success','Payroll deleted'); redirect(base_url('payroll/')); }
$employees=$pdo->query("SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name FROM employees e WHERE e.deleted_at IS NULL ORDER BY e.first_name")->fetchAll();
$rows=$pdo->query("SELECT p.*, CONCAT(e.first_name,' ',e.last_name) as emp_name FROM payroll p JOIN employees e ON e.id=p.employee_id ORDER BY p.year DESC, p.month DESC")->fetchAll();
layout_header('Payroll','Manage employee payroll','green','<button onclick="document.getElementById(\'dlg-create\').showModal()" class="btn btn-primary">New Payroll</button> <a href="'.base_url('payroll/items.php').'" class="btn btn-outline">Payroll Items</a>');
?>
<div class="card tab-notch tab-green"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Employee</th><th>Period</th><th>Base</th><th>Gross</th><th>Net</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<td style="font-weight:500;"><?= e($r['emp_name']) ?></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($r['month'].'/'.$r['year']) ?></td>
<td style="font-family:var(--font-mono);"><?= number_format($r['base_salary'],2) ?></td><td style="font-family:var(--font-mono);"><?= number_format($r['gross_salary'],2) ?></td><td style="font-family:var(--font-mono);font-weight:600;"><?= number_format($r['net_salary'],2) ?></td>
<td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
<td><div style="display:flex;gap:0.25rem;">
<button onclick="openEdit('<?= e($r['id']) ?>','<?= e($r['employee_id']) ?>','<?= (int)$r['month'] ?>','<?= (int)$r['year'] ?>','<?= e($r['base_salary']) ?>','<?= e($r['gross_salary']) ?>','<?= e($r['net_salary']) ?>','<?= e($r['status']) ?>')" class="btn btn-outline btn-sm" style="height:1.5rem;">Edit</button>
<a href="?delete=<?= e($r['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);">Delete</a>
</div></td>
</tr>
<?php endforeach; ?>
<?php if(empty($rows)): ?><tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No payroll records</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>

<dialog id="dlg-create" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">New Payroll</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
<div><label style="font-size:0.8125rem;">Employee *</label><select name="employee_id" required class="input"><option value="">Select</option><?php foreach($employees as $e): ?><option value="<?= e($e['id']) ?>"><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;"><div><label style="font-size:0.8125rem;">Month</label><input name="month" type="number" min="1" max="12" class="input" value="<?= date('n') ?>"></div><div><label style="font-size:0.8125rem;">Year</label><input name="year" type="number" class="input" value="<?= date('Y') ?>"></div></div>
<div><label style="font-size:0.8125rem;">Base Salary</label><input name="base_salary" type="number" step="0.01" class="input"></div>
<div><label style="font-size:0.8125rem;">Gross Salary</label><input name="gross_salary" type="number" step="0.01" class="input"></div>
<div><label style="font-size:0.8125rem;">Net Salary</label><input name="net_salary" type="number" step="0.01" class="input"></div>
<div><label style="font-size:0.8125rem;">Status</label><select name="status" class="input"><option>DRAFT</option><option>PROCESSED</option><option>PAID</option></select></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Create</button></div>
</form>
</dialog>
<dialog id="dlg-edit" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Edit Payroll</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit-id">
<div><label style="font-size:0.8125rem;">Month</label><input name="month" id="edit-month" type="number" min="1" max="12" class="input"></div>
<div><label style="font-size:0.8125rem;">Year</label><input name="year" id="edit-year" type="number" class="input"></div>
<div><label style="font-size:0.8125rem;">Base Salary</label><input name="base_salary" id="edit-base" type="number" step="0.01" class="input"></div>
<div><label style="font-size:0.8125rem;">Gross Salary</label><input name="gross_salary" id="edit-gross" type="number" step="0.01" class="input"></div>
<div><label style="font-size:0.8125rem;">Net Salary</label><input name="net_salary" id="edit-net" type="number" step="0.01" class="input"></div>
<div><label style="font-size:0.8125rem;">Status</label><select name="status" id="edit-status" class="input"><option>DRAFT</option><option>PROCESSED</option><option>PAID</option></select></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Save</button></div>
</form>
</dialog>
<script>function openEdit(id,emp,mon,yr,base,gross,net,status){ document.getElementById('edit-id').value=id; document.getElementById('edit-month').value=mon; document.getElementById('edit-year').value=yr; document.getElementById('edit-base').value=base; document.getElementById('edit-gross').value=gross; document.getElementById('edit-net').value=net; document.getElementById('edit-status').value=status; document.getElementById('dlg-edit').showModal(); }</script>
<?php layout_footer(); ?>
