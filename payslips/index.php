<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$empId=null; try{ $stmt=$pdo->prepare("SELECT id FROM employees WHERE user_id=? LIMIT 1"); $stmt->execute([$_SESSION['user']['id']]); $empId=$stmt->fetchColumn(); }catch(Exception $e){}
$payslips=[];
if($empId){ $stmt=$pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM payroll_items pi WHERE pi.payroll_id=p.id) as item_count FROM payroll p WHERE p.employee_id=? ORDER BY p.year DESC, p.month DESC"); $stmt->execute([$empId]); $payslips=$stmt->fetchAll(); }
elseif(in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR'])){ $payslips=$pdo->query("SELECT p.*, CONCAT(e.first_name,' ',e.last_name) as emp_name FROM payroll p JOIN employees e ON e.id=p.employee_id ORDER BY p.year DESC, p.month DESC LIMIT 20")->fetchAll(); }
layout_header('My Payslips','Your payroll history','green');
?>
<div class="card tab-notch tab-green"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Period</th><th>Gross Pay</th><th>Net Pay</th><th>Status</th><th>Items</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($payslips as $p): ?>
<tr>
<td style="font-family:var(--font-mono);"><?= e($p['month'].'/'.$p['year']) ?><?php if(isset($p['emp_name'])) echo '<div style="font-size:0.75rem;color:var(--muted-foreground);">'.e($p['emp_name']).'</div>'; ?></td>
<td style="font-family:var(--font-mono);"><?= number_format($p['gross_salary'],2) ?></td>
<td style="font-family:var(--font-mono);font-weight:600;"><?= number_format($p['net_salary'],2) ?></td>
<td><span class="badge <?= badge_class($p['status']) ?>"><?= e($p['status']) ?></span></td>
<td><?= isset($p['item_count'])?(int)$p['item_count']:'-' ?></td>
<td><button onclick="viewPayslip('<?= e($p['id']) ?>')" class="btn btn-outline btn-sm">View</button></td>
</tr>
<?php endforeach; ?>
<?php if(empty($payslips)): ?><tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No payslips</p><p style="font-size:0.8125rem;color:var(--muted-foreground);">Contact HR if you believe this is an error.</p></td></tr><?php endif; ?>
</tbody>
</table></div></div></div>
<dialog id="dlg-payslip" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:32rem;width:calc(100% - 2rem);"><div style="padding:1.25rem;"><h3 style="font-weight:600;">Payslip</h3><div id="payslip-body" style="margin-top:0.75rem;font-size:0.875rem;">Loading...</div><div style="display:flex;justify-content:flex-end;margin-top:1rem;"><button onclick="document.getElementById('dlg-payslip').close()" class="btn btn-ghost">Close</button><button onclick="window.print()" class="btn btn-primary">Print</button></div></div></dialog>
<script>
async function viewPayslip(id){
  const dlg=document.getElementById('dlg-payslip'); const body=document.getElementById('payslip-body');
  body.innerHTML='Loading...'; dlg.showModal();
  // simple: reload page with detail - here just show gross/net from table
  body.innerHTML='<p>Payslip ID: '+id+'</p><p>Details are available in payroll records. Please contact HR for full breakdown.</p>';
}
</script>
<?php layout_footer(); ?>
