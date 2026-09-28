<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$date = $_GET['date'] ?? date('Y-m-d');
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf() && isset($_POST['update_status'])){
    if(!in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'])){ flash('error','Not allowed'); redirect(base_url('attendance/?date='.e($date))); }
    $empId=$_POST['employee_id']??''; $status=$_POST['status']??'PRESENT';
    $checkIn = $status==='PRESENT' || $status==='LATE' ? date('Y-m-d H:i:s', strtotime($date.' 09:00:00')) : null;
    $checkOut= $status==='PRESENT' || $status==='LATE' ? date('Y-m-d H:i:s', strtotime($date.' 17:30:00')) : null;
    $pdo->prepare("INSERT INTO attendance (id,employee_id,date,check_in,check_out,status) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), check_in=VALUES(check_in), check_out=VALUES(check_out)")->execute([uuid(),$empId,$date,$checkIn,$checkOut,$status]);
    flash('success','Attendance updated');
    redirect(base_url('attendance/?date='.e($date)));
}
$prev = date('Y-m-d', strtotime($date.' -1 day')); $next = date('Y-m-d', strtotime($date.' +1 day'));
$holiday = $pdo->prepare("SELECT * FROM holidays WHERE date=?"); $holiday->execute([$date]); $hol=$holiday->fetch();
$employees = $pdo->query("SELECT e.*, d.name as dept_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.deleted_at IS NULL AND e.status='ACTIVE' ORDER BY e.first_name")->fetchAll();
// fetch attendance for date
$attMap=[]; $stmt=$pdo->prepare("SELECT * FROM attendance WHERE date=?"); $stmt->execute([$date]); foreach($stmt->fetchAll() as $r) $attMap[$r['employee_id']]=$r;
// fetch approved leaves overlapping date
$leaveMap=[]; $stmt=$pdo->prepare("SELECT employee_id FROM leave_requests WHERE status='APPROVED' AND ? BETWEEN start_date AND end_date"); $stmt->execute([$date]); foreach($stmt->fetchAll() as $r) $leaveMap[$r['employee_id']]=true;
layout_header('Attendance','Daily attendance — '.e(date('l, M j, Y', strtotime($date))),'teal');
?>
<div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap;">
  <a href="?date=<?= e($prev) ?>" class="btn btn-outline btn-sm"><i data-lucide="chevron-left" style="width:0.875rem;height:0.875rem;"></i></a>
  <form method="GET" style="display:flex;gap:0.5rem;align-items:center;"><input type="date" name="date" value="<?= e($date) ?>" class="input" style="width:auto;"><button class="btn btn-outline btn-sm">Go</button></form>
  <a href="?date=<?= e($next) ?>" class="btn btn-outline btn-sm"><i data-lucide="chevron-right" style="width:0.875rem;height:0.875rem;"></i></a>
  <?php if($hol): ?><span class="badge badge-blue">Holiday: <?= e($hol['name']) ?></span><?php endif; ?>
  <a href="<?= base_url('attendance/holidays.php') ?>" class="btn btn-ghost btn-sm">Holidays</a>
</div>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Employee</th><th>Department</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($employees as $e): $att=$attMap[$e['id']]??null; $isOnLeave=isset($leaveMap[$e['id']]); $status = $isOnLeave ? 'ON_LEAVE' : ($hol ? 'HOLIDAY' : ($att['status']??'ABSENT')); ?>
<tr>
<td><div style="font-weight:500;"><?= e($e['first_name'].' '.$e['last_name']) ?></div><div style="font-size:0.6875rem;color:var(--muted-foreground);font-family:var(--font-mono);"><?= e($e['employee_code']) ?></div></td>
<td><?= e($e['dept_name']??'-') ?></td>
<td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= $att['check_in'] ? e(date('H:i:s', strtotime($att['check_in']))) : '-' ?></td>
<td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= $att['check_out'] ? e(date('H:i:s', strtotime($att['check_out']))) : '-' ?></td>
<td><span class="badge <?= $status==='PRESENT'?'badge-green':($status==='ABSENT'?'badge-brick':($status==='ON_LEAVE'?'badge-slate':($status==='HOLIDAY'?'badge-blue':'badge-brass'))) ?>"><?= e($status) ?></span></td>
<td>
<?php if(!$isOnLeave && !$hol && in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'])): ?>
<form method="POST" style="display:flex;gap:0.25rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="employee_id" value="<?= e($e['id']) ?>">
<button name="update_status" value="PRESENT" onclick="this.form.status.value='PRESENT'" class="btn btn-ghost btn-sm" style="height:1.5rem;">Present</button>
<input type="hidden" name="status" value="PRESENT">
<select name="status" class="input" style="height:1.5rem;font-size:0.7rem;width:6.5rem;"><option>PRESENT</option><option>ABSENT</option><option>LATE</option><option>HALF_DAY</option></select>
<button name="update_status" class="btn btn-outline btn-sm" style="height:1.5rem;">Save</button>
</form>
<?php else: ?><span style="font-size:0.75rem;color:var(--muted-foreground);">—</span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div></div></div>
<?php layout_footer(); ?>
