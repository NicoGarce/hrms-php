<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$month = $_GET['month'] ?? date('Y-m');
$start = $month.'-01'; $end = date('Y-m-t', strtotime($start));
$requests=$pdo->query("SELECT lr.*, CONCAT(e.first_name,' ',e.last_name) as emp_name FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.status IN ('APPROVED','DEPARTMENT_APPROVED') AND lr.start_date <= '$end' AND lr.end_date >= '$start' ORDER BY lr.start_date")->fetchAll();
$holidays=$pdo->query("SELECT * FROM holidays WHERE date BETWEEN '$start' AND '$end' ORDER BY date")->fetchAll();
layout_header('Leave Calendar','Month view — '.e(date('F Y', strtotime($start))),'teal','<a href="'.base_url('leave/').'" class="btn btn-outline">Back</a>');
?>
<div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:1rem;">
<form method="GET" style="display:flex;gap:0.5rem;align-items:center;"><input type="month" name="month" value="<?= e($month) ?>" class="input" style="width:auto;"><button class="btn btn-outline btn-sm">Go</button></form>
</div>
<div class="card tab-notch tab-teal"><div class="card-content">
<h3 style="font-weight:600;margin-bottom:0.5rem;">Approved Leaves</h3>
<?php if(empty($requests)): ?><p style="color:var(--muted-foreground);font-size:0.875rem;">No approved leaves this month</p><?php else: foreach($requests as $r): ?>
<div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;border:1px solid var(--border);border-radius:0.5rem;margin-bottom:0.5rem;font-size:0.875rem;"><span><strong><?= e($r['emp_name']) ?></strong> — <?= e($r['start_date']) ?> to <?= e($r['end_date']) ?></span><span class="badge badge-green"><?= e($r['status']) ?></span></div>
<?php endforeach; endif; ?>
<h3 style="font-weight:600;margin:1rem 0 0.5rem;">Holidays</h3>
<?php if(empty($holidays)): ?><p style="color:var(--muted-foreground);font-size:0.875rem;">No holidays this month</p><?php else: foreach($holidays as $h): ?>
<div style="padding:0.5rem;border:1px solid var(--border);border-radius:0.5rem;margin-bottom:0.5rem;font-size:0.875rem;"><strong><?= e($h['name']) ?></strong> — <?= e($h['date']) ?> <span class="badge badge-slate"><?= e($h['type']) ?></span></div>
<?php endforeach; endif; ?>
</div></div>
<?php layout_footer(); ?>
