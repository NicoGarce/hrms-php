<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$rows=$pdo->query("SELECT p.*, CONCAT(e.first_name,' ',e.last_name) as emp_name FROM payroll p JOIN employees e ON e.id=p.employee_id ORDER BY p.year DESC, p.month DESC LIMIT 20")->fetchAll();
layout_header('Payroll Report','Recent payrolls','brick','<a href="'.base_url('reports/').'" class="btn btn-outline">Back</a>');
?>
<div class="card tab-notch tab-brick"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Employee</th><th>Period</th><th>Base</th><th>Gross</th><th>Net</th><th>Status</th></tr></thead>
<tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['emp_name']) ?></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($r['month'].'/'.$r['year']) ?></td><td style="font-family:var(--font-mono);"><?= number_format($r['base_salary'],2) ?></td><td style="font-family:var(--font-mono);"><?= number_format($r['gross_salary'],2) ?></td><td style="font-family:var(--font-mono);font-weight:600;"><?= number_format($r['net_salary'],2) ?></td><td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td></tr><?php endforeach; ?>
<?php if(empty($rows)): ?><tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No payroll data</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>
<?php layout_footer(); ?>
