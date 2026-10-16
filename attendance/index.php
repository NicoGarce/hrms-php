<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$date = $_GET['date'] ?? date('Y-m-d');
$isHR = in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'], true);

// Template for the Excel/CSV import (companies with their own tracking).
if(isset($_GET['template']) && $_GET['template']==='csv' && $isHR){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-template.csv"');
    $out=fopen('php://output','w');
    fputcsv($out, ['employee_number','date','check_in','check_out','status']);
    fputcsv($out, ['2026-0005','2026-10-15','09:00','17:30','PRESENT']);
    fclose($out); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf() && isset($_POST['import_attendance'])){
    if(!$isHR){ flash('error','Not allowed'); redirect(base_url('attendance/?date='.$date)); }
    $err='';
    if(!isset($_FILES['import_file']) || $_FILES['import_file']['error']!==UPLOAD_ERR_OK) $err='Choose a CSV file to import.';
    else{
        $ext=strtolower(pathinfo($_FILES['import_file']['name']??'', PATHINFO_EXTENSION));
        if($ext!=='csv') $err='Only .csv files are accepted (save your Excel sheet as CSV).';
        elseif(($_FILES['import_file']['size']??0)>2*1024*1024) $err='File too large (max 2 MB).';
    }
    if($err){ flash('error',$err); redirect(base_url('attendance/?date='.$date)); }
    $handle=fopen($_FILES['import_file']['tmp_name'],'r');
    $header=array_map(fn($h)=>strtolower(trim($h)), fgetcsv($handle) ?: []);
    $expected=['employee_number','date','check_in','check_out','status'];
    if($header!==$expected){ fclose($handle); flash('error','Bad header. Use the template: '.implode(',', $expected)); redirect(base_url('attendance/?date='.$date)); }
    $ok=0; $errors=[];
    $empCache=[];
    while(($row=fgetcsv($handle))!==false){
        if(count(array_filter($row, fn($v)=>trim((string)$v)!==''))===0) continue;
        [$empNo,$d,$ci,$co,$st]=array_pad(array_map('trim',$row),5,'');
        $st=strtoupper($st);
        if(!in_array($st,['PRESENT','ABSENT','LATE','HALF_DAY'],true)){ $errors[]="Row $empNo/$d: bad status"; continue; }
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)){ $errors[]="Row $empNo/$d: bad date"; continue; }
        if(!isset($empCache[$empNo])){
            $s=$pdo->prepare("SELECT id FROM employees WHERE employee_code=? AND deleted_at IS NULL LIMIT 1");
            $s->execute([$empNo]); $empCache[$empNo]=$s->fetchColumn();
        }
        if(!$empCache[$empNo]){ $errors[]="Row $empNo/$d: unknown employee number"; continue; }
        $checkIn=$ci!==''?date('Y-m-d H:i:s', strtotime($d.' '.$ci)):null;
        $checkOut=$co!==''?date('Y-m-d H:i:s', strtotime($d.' '.$co)):null;
        try{
            $pdo->prepare("INSERT INTO attendance (id,employee_id,date,check_in,check_out,status) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), check_in=VALUES(check_in), check_out=VALUES(check_out)")->execute([uuid(),$empCache[$empNo],$d,$checkIn,$checkOut,$st]);
            $ok++;
        }catch(Exception $e){ $errors[]="Row $empNo/$d: save failed"; }
        if(count($errors)>=20){ $errors[]='…more errors truncated'; break; }
    }
    fclose($handle);
    audit_log('IMPORT','attendance',null,['imported'=>$ok,'errors'=>count($errors)]);
    if($errors) flash('error',"Imported $ok row(s). Errors: ".implode('; ', array_slice($errors,0,5)));
    else flash('success',"Imported $ok row(s).");
    redirect(base_url('attendance/?date='.$date));
}
// Tardiness notice to an employee (3+ LATE in the last 30 days).
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf() && isset($_POST['send_tardiness']) && $isHR){
    $empId=$_POST['employee_id']??'';
    $e=$pdo->prepare("SELECT user_id, first_name, last_name FROM employees WHERE id=? AND deleted_at IS NULL LIMIT 1");
    $e->execute([$empId]); $empRow=$e->fetch();
    if($empRow){
        $recent=$pdo->prepare("SELECT id FROM notifications WHERE user_id=? AND type='tardiness_notice' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 1");
        $recent->execute([$empRow['user_id']]);
        if(!$recent->fetch()){
            $pdo->prepare("INSERT INTO notifications (id,user_id,type,title,message) VALUES (?,?,?,?,?)")
                ->execute([uuid(),$empRow['user_id'],'tardiness_notice','Tardiness warning','You have been late 3 or more times in the last 30 days. Repeated tardiness affects performance ratings. Please coordinate with your Department Head.']);
            audit_log('NOTICE','attendance',$empId,['tardiness'=>true]);
            flash('success','Tardiness notice sent to '.$empRow['first_name'].' '.$empRow['last_name']);
        } else flash('error','A tardiness notice was already sent in the last 30 days.');
    }
    redirect(base_url('attendance/?date='.$date));
}
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
layout_header();
?>
<p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1rem;"><?= e(date('l, F j, Y', strtotime($date))) ?></p>
<div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap;">
  <a href="?date=<?= e($prev) ?>" class="btn btn-outline btn-sm"><i data-lucide="chevron-left" style="width:0.875rem;height:0.875rem;"></i></a>
  <form method="GET" style="display:flex;gap:0.5rem;align-items:center;"><input type="date" name="date" value="<?= e($date) ?>" class="input" style="width:auto;"><button class="btn btn-outline btn-sm">Go</button></form>
  <a href="?date=<?= e($next) ?>" class="btn btn-outline btn-sm"><i data-lucide="chevron-right" style="width:0.875rem;height:0.875rem;"></i></a>
  <?php if($hol): ?><span class="badge badge-blue">Holiday: <?= e($hol['name']) ?></span><?php endif; ?>
  <a href="<?= base_url('attendance/holidays.php') ?>" class="btn btn-ghost btn-sm">Holidays</a>
  <?php if($isHR): ?>
  <a href="?date=<?= e($date) ?>&template=csv" class="btn btn-ghost btn-sm">CSV template</a>
  <button onclick="document.getElementById('dlg-import').showModal()" class="btn btn-outline btn-sm">Excel/CSV Import</button>
  <?php endif; ?>
</div>
<?php
// Tardiness warnings: 3+ LATE arrivals in the last 30 days.
$tardy=[];
try{
    $tardy=$pdo->query("SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name, e.employee_code, COUNT(a.id) as lates FROM employees e JOIN attendance a ON a.employee_id=e.id AND a.status='LATE' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) WHERE e.deleted_at IS NULL GROUP BY e.id HAVING lates>=3 ORDER BY lates DESC")->fetchAll() ?: [];
}catch(Exception $e){}
?>
<?php if($tardy): ?>
<div class="card tab-notch tab-brick" style="margin-bottom:1rem;"><div class="card-header"><div class="card-title">Tardiness warnings (<?= count($tardy) ?>)</div><div class="card-desc">3+ late arrivals in the last 30 days — warnings feed into performance ratings</div></div>
<div class="card-content" style="padding:0;"><div class="table-wrap"><table><thead><tr><th>Employee</th><th>Number</th><th>Lates (30d)</th><th>Action</th></tr></thead><tbody>
<?php foreach($tardy as $t): ?><tr><td style="font-weight:500;"><?= e($t['name']) ?></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($t['employee_code']) ?></td><td><span class="badge badge-brick"><?= (int)$t['lates'] ?> late</span></td>
<td><?php if($isHR): ?><form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="employee_id" value="<?= e($t['id']) ?>"><button name="send_tardiness" class="btn btn-outline btn-sm" style="height:1.5rem;" onclick="return confirm('Send a tardiness notice to this employee?')">Send notice</button></form><?php else: ?><span style="font-size:0.75rem;color:var(--muted-foreground);">HR notified</span><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php endif; ?>
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
<?php if($isHR): ?>
<dialog id="dlg-import" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" enctype="multipart/form-data" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Import Attendance (Excel → CSV)</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<p style="font-size:0.75rem;color:var(--muted-foreground);">For companies with their own attendance tracking. In Excel use <em>Save As → CSV</em> with columns: <span style="font-family:var(--font-mono);">employee_number,date,check_in,check_out,status</span>. Status: PRESENT, ABSENT, LATE, HALF_DAY.</p>
<div><label style="font-size:0.8125rem;">CSV file (max 2 MB)</label><input type="file" name="import_file" accept=".csv" class="input" required></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="import_attendance" class="btn btn-primary">Import</button></div>
</form>
</dialog>
<?php endif; ?>
<?php layout_footer(); ?>
