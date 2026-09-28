<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $payroll=$_POST['payroll_id']??''; $type=$_POST['type']??'ALLOWANCE'; $name=trim($_POST['name']??''); $amt=(float)($_POST['amount']??0);
    if($payroll&&$name){ $pdo->prepare("INSERT INTO payroll_items (id,payroll_id,type,name,amount) VALUES (?,?,?,?,?)")->execute([uuid(),$payroll,$type,$name,$amt]); flash('success','Item added'); }
    redirect(base_url('payroll/items.php'));
}
if(isset($_GET['delete'])){ $pdo->prepare("DELETE FROM payroll_items WHERE id=?")->execute([$_GET['delete']]); flash('success','Item deleted'); redirect(base_url('payroll/items.php')); }
$payrolls=$pdo->query("SELECT p.id, CONCAT(e.first_name,' ',e.last_name,' - ',p.month,'/',p.year) as label FROM payroll p JOIN employees e ON e.id=p.employee_id ORDER BY p.year DESC, p.month DESC")->fetchAll();
$items=$pdo->query("SELECT pi.*, CONCAT(e.first_name,' ',e.last_name) as emp_name, p.month, p.year FROM payroll_items pi JOIN payroll p ON p.id=pi.payroll_id JOIN employees e ON e.id=p.employee_id ORDER BY pi.created_at DESC")->fetchAll();
layout_header('Payroll Items','Allowances, deductions & bonuses','green','<a href="'.base_url('payroll/').'" class="btn btn-outline">Back</a>');
?>
<div class="card tab-notch tab-green"><div class="card-content">
<form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.5rem;align-items:end;margin-bottom:1rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;">Payroll *</label><select name="payroll_id" required class="input"><option value="">Select</option><?php foreach($payrolls as $pr): ?><option value="<?= e($pr['id']) ?>"><?= e($pr['label']) ?></option><?php endforeach; ?></select></div>
<div><label style="font-size:0.8125rem;">Type</label><select name="type" class="input"><option>ALLOWANCE</option><option>DEDUCTION</option><option>BONUS</option></select></div>
<div><label style="font-size:0.8125rem;">Name *</label><input name="name" required class="input" placeholder="Housing"></div>
<div><label style="font-size:0.8125rem;">Amount</label><input name="amount" type="number" step="0.01" class="input"></div>
<div><button class="btn btn-primary" style="width:100%;">Add Item</button></div>
</form>
<div class="table-wrap"><table>
<thead><tr><th>Employee</th><th>Period</th><th>Type</th><th>Name</th><th>Amount</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($items as $it): ?><tr><td><?= e($it['emp_name']) ?></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($it['month'].'/'.$it['year']) ?></td><td><span class="badge <?= $it['type']==='ALLOWANCE'?'badge-green':($it['type']==='DEDUCTION'?'badge-brick':'badge-brass') ?>"><?= e($it['type']) ?></span></td><td><?= e($it['name']) ?></td><td style="font-family:var(--font-mono);"><?= number_format($it['amount'],2) ?></td><td><a href="?delete=<?= e($it['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="color:var(--destructive);">Delete</a></td></tr><?php endforeach; ?>
<?php if(empty($items)): ?><tr><td colspan="6" style="text-align:center;padding:1.5rem;color:var(--muted-foreground);">No items</td></tr><?php endif; ?>
</tbody>
</table></div>
</div></div>
<?php layout_footer(); ?>
