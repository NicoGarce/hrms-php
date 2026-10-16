<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$role=$_SESSION['user']['role'];
$canSchedule=in_array($role,['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],true);
$canConduct=in_array($role,['SUPER_ADMINISTRATOR','DEPARTMENT_HEAD'],true);

$tardyMap=[];
try{
    foreach($pdo->query("SELECT employee_id, COUNT(*) as lates FROM attendance WHERE status='LATE' AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY employee_id")->fetchAll() as $r) $tardyMap[$r['employee_id']]=(int)$r['lates'];
}catch(Exception $e){}

if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    // Server-side role gates (UI buttons are hidden, but the endpoints enforce too).
    if(isset($_POST['schedule_review']) && !$canSchedule){ flash('error','Only Super Admin / HR Admin can schedule reviews'); redirect(base_url('performance/')); }
    if(isset($_POST['conduct_review']) && !$canConduct){ flash('error','Only Department Heads can conduct reviews'); redirect(base_url('performance/')); }
    // Super Admin / HR Admin set the review date (schedule only, no rating).
    if(isset($_POST['schedule_review']) && $canSchedule){
        $emp=$_POST['employee_id']??''; $period=trim($_POST['review_period']??''); $rdate=trim($_POST['review_date']??'')?:null;
        $goals=trim($_POST['goals']??'');
        $reviewerEmpId=null; try{ $stmt=$pdo->prepare("SELECT id FROM employees WHERE user_id=? LIMIT 1"); $stmt->execute([$_SESSION['user']['id']]); $reviewerEmpId=$stmt->fetchColumn(); }catch(Exception $e){}
        if($emp&&$period){ $pdo->prepare("INSERT INTO performance_reviews (id,employee_id,reviewer_id,review_period,goals,rating,feedback,review_date,status) VALUES (?,?,?,?,?,NULL,NULL,?,'DRAFT')")->execute([uuid(),$emp,$reviewerEmpId?:$emp,$period,$goals?:null,$rdate]); audit_log('CREATE','performance_reviews',null,['employee'=>$emp,'date'=>$rdate]); flash('success','Review scheduled'); }
        redirect(base_url('performance/'));
    }
    // Department Heads conduct the review (rating + feedback, tardiness considered).
    if(isset($_POST['conduct_review']) && $canConduct){
        $id=$_POST['id']??''; $rating=(int)($_POST['rating']??0)?:null; $feedback=trim($_POST['feedback']??''); $goals=trim($_POST['goals']??''); $rdate=trim($_POST['review_date']??'')?:null;
        $rv=$pdo->prepare("SELECT * FROM performance_reviews WHERE id=? LIMIT 1"); $rv->execute([$id]); $row=$rv->fetch();
        if(!$row){ flash('error','Review not found'); redirect(base_url('performance/')); }
        $lates=$tardyMap[$row['employee_id']]??0;
        if($rating===null){ flash('error','Rating is required to conduct a review'); redirect(base_url('performance/')); }
        $reviewerEmpId=$row['reviewer_id']; try{ $stmt=$pdo->prepare("SELECT id FROM employees WHERE user_id=? LIMIT 1"); $stmt->execute([$_SESSION['user']['id']]); $found=$stmt->fetchColumn(); if($found) $reviewerEmpId=$found; }catch(Exception $e){}
        $pdo->prepare("UPDATE performance_reviews SET reviewer_id=?, rating=?, feedback=? WHERE id=?")->execute([$reviewerEmpId,$rating,$feedback?:null,$id]);
        if($goals!=='') $pdo->prepare("UPDATE performance_reviews SET goals=? WHERE id=?")->execute([$goals,$id]);
        if($rdate) $pdo->prepare("UPDATE performance_reviews SET review_date=? WHERE id=?")->execute([$rdate,$id]);
        $pdo->prepare("UPDATE performance_reviews SET status='SUBMITTED' WHERE id=? AND status='DRAFT'")->execute([$id]);
        audit_log('UPDATE','performance_reviews',$id,['rating'=>$rating,'lates_90d'=>$lates]);
        if($lates>=3) flash('success','Review submitted. Note: '.$lates.' late arrivals in 90 days were factored in.');
        else flash('success','Review submitted');
        redirect(base_url('performance/'));
    }
    if(isset($_POST['acknowledge'])){
        $id=$_POST['id']??''; $pdo->prepare("UPDATE performance_reviews SET status='REVIEWED' WHERE id=?")->execute([$id]); audit_log('ACK','performance_reviews',$id); flash('success','Acknowledged'); redirect(base_url('performance/'));
    }
}
$employees=$pdo->query("SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name FROM employees e WHERE e.deleted_at IS NULL ORDER BY e.first_name")->fetchAll();
$sql="SELECT pr.*, CONCAT(e.first_name,' ',e.last_name) as emp_name, CONCAT(r.first_name,' ',r.last_name) as reviewer_name FROM performance_reviews pr JOIN employees e ON e.id=pr.employee_id LEFT JOIN employees r ON r.id=pr.reviewer_id ORDER BY pr.created_at DESC";
$reviews=$pdo->query($sql)->fetchAll();
layout_header();
?>
<?php if($canSchedule || $canConduct): ?>
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
  <?php if($canSchedule): ?><button onclick="document.getElementById('dlg-schedule').showModal()" class="btn btn-outline">Schedule Review</button><?php endif; ?>
  <?php if($canConduct): ?><button onclick="document.getElementById('dlg-conduct').showModal()" class="btn btn-primary">Conduct Review</button><?php endif; ?>
</div>
<?php endif; ?>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Employee</th><th>Reviewer</th><th>Period</th><th>Date</th><th>Lates (90d)</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($reviews as $rv): $lates=$tardyMap[$rv['employee_id']]??0; ?>
<tr>
<td><?= e($rv['emp_name']) ?></td><td><?= e($rv['reviewer_name']??'-') ?></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($rv['review_period']) ?></td>
<td style="font-size:0.8125rem;"><?= $rv['review_date']?e(date('M d, Y',strtotime($rv['review_date']))):'-' ?></td>
<td><?php if($lates>=3): ?><span class="badge badge-brick"><?= (int)$lates ?> late</span><?php else: ?><span style="color:var(--muted-foreground);font-size:0.8125rem;"><?= (int)$lates ?></span><?php endif; ?></td>
<td><?php if($rv['rating']){ for($i=0;$i<5;$i++) echo $i<$rv['rating']?'★':'☆'; } else echo '-'; ?></td>
<td><span class="badge <?= badge_class($rv['status']) ?>"><?= e($rv['status']) ?></span></td>
<td>
<?php
$currentEmpId=null; try{ $s=$pdo->prepare("SELECT id FROM employees WHERE user_id=?"); $s->execute([$_SESSION['user']['id']]); $currentEmpId=$s->fetchColumn(); }catch(Exception $e){}
if($currentEmpId==$rv['employee_id'] && $rv['status']==='SUBMITTED'): ?>
<form method="POST"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($rv['id']) ?>"><button name="acknowledge" class="btn btn-primary btn-sm" style="height:1.5rem;">Acknowledge</button></form>
<?php elseif($canConduct && in_array($rv['status'],['DRAFT','SUBMITTED'],true)): ?>
<button onclick="openConduct('<?= e($rv['id']) ?>','<?= e($rv['emp_name']) ?>','<?= $rv['rating']?(int)$rv['rating']:'' ?>')" class="btn btn-outline btn-sm" style="height:1.5rem;">Rate</button>
<?php else: ?><span style="font-size:0.75rem;color:var(--muted-foreground);max-width:10rem;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e(substr($rv['feedback']??'',0,40)) ?></span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($reviews)): ?><tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No reviews</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>
<p style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.5rem;">Tardiness (3+ lates in 90 days) must be reflected in ratings. Only Department Heads (and Super Admin) conduct reviews; HR schedules review dates.</p>

<?php if($canSchedule): ?>
<dialog id="dlg-schedule" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Schedule Review (HR)</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;">Employee *</label><select name="employee_id" required class="input"><option value="">Select</option><?php foreach($employees as $e): ?><option value="<?= e($e['id']) ?>"><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
<div><label style="font-size:0.8125rem;">Review Period *</label><input name="review_period" required class="input" placeholder="Q1 2024"></div>
<div><label style="font-size:0.8125rem;">Review Date *</label><input name="review_date" type="date" required class="input"></div>
<div><label style="font-size:0.8125rem;">Goals</label><textarea name="goals" class="input" rows="2"></textarea></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="schedule_review" class="btn btn-outline">Schedule</button></div>
</form>
</dialog>
<?php endif; ?>
<?php if($canConduct): ?>
<dialog id="dlg-conduct" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Conduct Review (Department Head)</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;">Review *</label><select name="id" id="conduct-id" required class="input"><option value="">Select</option><?php foreach($reviews as $rv): if(!in_array($rv['status'],['DRAFT','SUBMITTED'],true)) continue; ?><option value="<?= e($rv['id']) ?>"><?= e($rv['emp_name'].' — '.$rv['review_period']) ?><?= ($tardyMap[$rv['employee_id']]??0)>=3?' ('.($tardyMap[$rv['employee_id']]).' lates!)':'' ?></option><?php endforeach; ?></select></div>
<div><label style="font-size:0.8125rem;">Rating (1-5) *</label><div style="display:flex;gap:0.25rem;"><?php for($i=1;$i<=5;$i++): ?><label style="cursor:pointer;"><input type="radio" name="rating" value="<?= $i ?>" required> <?= $i ?>★</label><?php endfor; ?></div></div>
<div><label style="font-size:0.8125rem;">Goals</label><textarea name="goals" class="input" rows="2"></textarea></div>
<div><label style="font-size:0.8125rem;">Feedback</label><textarea name="feedback" class="input" rows="2"></textarea></div>
<div><label style="font-size:0.8125rem;">Review Date</label><input name="review_date" type="date" class="input"></div>
<p style="font-size:0.72rem;color:var(--muted-foreground);">Factor in tardiness: 3+ lates in 90 days should lower the rating.</p>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="conduct_review" class="btn btn-primary">Submit Review</button></div>
</form>
</dialog>
<script>function openConduct(id,name,rating){ var s=document.getElementById('conduct-id'); if(s) s.value=id; document.getElementById('dlg-conduct').showModal(); }</script>
<?php endif; ?>
<?php layout_footer(); ?>
