<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
// Determine current employee id for leave creation
$currentEmp = null;
try { $stmt=$pdo->prepare("SELECT id FROM employees WHERE user_id=? LIMIT 1"); $stmt->execute([$_SESSION['user']['id']]); $currentEmp=$stmt->fetchColumn(); } catch(Exception $e){}
$statusFilter=$_GET['status']??'ALL';
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    if(isset($_POST['create_leave'])){
        $empId = $_POST['employee_id'] ?? $currentEmp;
        // employees can only create for themselves unless admin
        if(!in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']) && $empId != $currentEmp) $empId=$currentEmp;
        $lt=$_POST['leave_type_id']??''; $start=$_POST['start_date']??''; $end=$_POST['end_date']??''; $reason=trim($_POST['reason']??'');
        if($empId&&$lt&&$start&&$end&&$reason){ $pdo->prepare("INSERT INTO leave_requests (id,employee_id,leave_type_id,start_date,end_date,reason,status) VALUES (?,?,?,?,?,?,?)")->execute([uuid(),$empId,$lt,$start,$end,$reason,'PENDING']); flash('success','Leave request created'); }
        redirect(base_url('leave/'));
    }
    if(isset($_POST['approve'])){
        $id=$_POST['id']??''; $role=$_SESSION['user']['role'];
        $req=$pdo->prepare("SELECT * FROM leave_requests WHERE id=?"); $req->execute([$id]); $r=$req->fetch();
        if($r){
            if($role==='DEPARTMENT_HEAD' && $r['status']==='PENDING'){ $pdo->prepare("UPDATE leave_requests SET status='DEPARTMENT_APPROVED', approved_by=? WHERE id=?")->execute([$_SESSION['user']['id'],$id]); }
            elseif(in_array($role,['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']) && $r['status']==='DEPARTMENT_APPROVED'){ $pdo->prepare("UPDATE leave_requests SET status='APPROVED', approved_by=? WHERE id=?")->execute([$_SESSION['user']['id'],$id]); }
            elseif($role==='SUPER_ADMINISTRATOR' && $r['status']==='PENDING'){ $pdo->prepare("UPDATE leave_requests SET status='APPROVED', approved_by=? WHERE id=?")->execute([$_SESSION['user']['id'],$id]); }
            flash('success','Leave approved');
        }
        redirect(base_url('leave/?status='.e($statusFilter)));
    }
    if(isset($_POST['reject'])){
        $id=$_POST['id']??''; $pdo->prepare("UPDATE leave_requests SET status='REJECTED' WHERE id=?")->execute([$id]); flash('success','Leave rejected'); redirect(base_url('leave/?status='.e($statusFilter)));
    }
}
$leaveTypes=$pdo->query("SELECT * FROM leave_types ORDER BY name")->fetchAll();
$employees=$pdo->query("SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name FROM employees e WHERE e.deleted_at IS NULL ORDER BY e.first_name")->fetchAll();
$sql="SELECT lr.*, CONCAT(e.first_name,' ',e.last_name) as emp_name, lt.name as type_name FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE 1=1";
$params=[];
if($statusFilter!=='ALL'){ $sql.=" AND lr.status=?"; $params[]=$statusFilter; }
// employees see only own unless admin
if(!in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']) && $currentEmp){ $sql.=" AND lr.employee_id=?"; $params[]=$currentEmp; }
$sql.=" ORDER BY lr.created_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $requests=$stmt->fetchAll();
function canApprove($req, $role){
    if($role==='DEPARTMENT_HEAD' && $req['status']==='PENDING') return true;
    if(in_array($role,['HR_ADMINISTRATOR']) && $req['status']==='DEPARTMENT_APPROVED') return true;
    if($role==='SUPER_ADMINISTRATOR' && in_array($req['status'],['PENDING','DEPARTMENT_APPROVED'])) return true;
    return false;
}
layout_header('Leave','Manage leave requests','teal','<button onclick="document.getElementById(\'dlg-create\').showModal()" class="btn btn-primary">New Request</button> <a href="'.base_url('leave/types.php').'" class="btn btn-outline">Leave Types</a> <a href="'.base_url('leave/calendar.php').'" class="btn btn-outline">Calendar</a>');
?>
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
  <?php foreach(['ALL','PENDING','DEPARTMENT_APPROVED','APPROVED','REJECTED'] as $s): ?>
  <a href="?status=<?= e($s) ?>" class="btn <?= $statusFilter==$s?'btn-primary':'btn-outline' ?> btn-sm"><?= e($s) ?></a>
  <?php endforeach; ?>
</div>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($requests as $r): ?>
<tr>
<td style="font-weight:500;"><?= e($r['emp_name']) ?></td><td><?= e($r['type_name']) ?></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e(date('M d', strtotime($r['start_date']))) ?> – <?= e(date('M d, Y', strtotime($r['end_date']))) ?></td>
<td style="max-width:18rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.8125rem;"><?= e($r['reason']) ?></td>
<td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
<td>
<?php if(canApprove($r, $_SESSION['user']['role'])): ?>
<form method="POST" style="display:flex;gap:0.25rem;">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
<button name="approve" class="btn btn-primary btn-sm" style="height:1.5rem;">Approve</button>
<button name="reject" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);">Reject</button>
</form>
<?php else: ?><span style="font-size:0.75rem;color:var(--muted-foreground);">—</span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($requests)): ?><tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No leave requests</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>

<dialog id="dlg-create" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">New Leave Request</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<?php if(in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'])): ?>
<div><label style="font-size:0.8125rem;">Employee *</label><select name="employee_id" class="input"><option value="">Select</option><?php foreach($employees as $e): ?><option value="<?= e($e['id']) ?>" <?= $currentEmp==$e['id']?'selected':'' ?>><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
<?php else: ?><input type="hidden" name="employee_id" value="<?= e($currentEmp) ?>"><?php endif; ?>
<div><label style="font-size:0.8125rem;">Leave Type *</label><select name="leave_type_id" required class="input"><option value="">Select</option><?php foreach($leaveTypes as $lt): ?><option value="<?= e($lt['id']) ?>"><?= e($lt['name']) ?> (<?= (int)$lt['days_allowed'] ?> days)</option><?php endforeach; ?></select></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;"><div><label style="font-size:0.8125rem;">Start Date *</label><input name="start_date" type="date" required class="input"></div><div><label style="font-size:0.8125rem;">End Date *</label><input name="end_date" type="date" required class="input"></div></div>
<div><label style="font-size:0.8125rem;">Reason *</label><textarea name="reason" required class="input" rows="3"></textarea></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="create_leave" class="btn btn-primary">Submit</button></div>
</form>
</dialog>
<?php layout_footer(); ?>
