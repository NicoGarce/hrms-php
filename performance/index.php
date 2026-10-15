<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$role=$_SESSION['user']['role'];
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    if(isset($_POST['create_review'])){
        $emp=$_POST['employee_id']??''; $period=trim($_POST['review_period']??''); $rating=(int)($_POST['rating']??0)?:null; $goals=trim($_POST['goals']??''); $feedback=trim($_POST['feedback']??''); $status=isset($_POST['is_draft'])?'DRAFT':'SUBMITTED';
        $reviewerEmpId=null; try{ $stmt=$pdo->prepare("SELECT id FROM employees WHERE user_id=? LIMIT 1"); $stmt->execute([$_SESSION['user']['id']]); $reviewerEmpId=$stmt->fetchColumn(); }catch(Exception $e){}
        if($emp&&$period){ $pdo->prepare("INSERT INTO performance_reviews (id,employee_id,reviewer_id,review_period,goals,rating,feedback,status) VALUES (?,?,?,?,?,?,?,?)")->execute([uuid(),$emp,$reviewerEmpId?:$emp,$period,$goals?:null,$rating,$feedback?:null,$status]); flash('success','Review created'); }
        redirect(base_url('performance/'));
    }
    if(isset($_POST['acknowledge'])){
        $id=$_POST['id']??''; $pdo->prepare("UPDATE performance_reviews SET status='REVIEWED' WHERE id=?")->execute([$id]); flash('success','Acknowledged'); redirect(base_url('performance/'));
    }
}
$employees=$pdo->query("SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name FROM employees e WHERE e.deleted_at IS NULL ORDER BY e.first_name")->fetchAll();
$sql="SELECT pr.*, CONCAT(e.first_name,' ',e.last_name) as emp_name, CONCAT(r.first_name,' ',r.last_name) as reviewer_name FROM performance_reviews pr JOIN employees e ON e.id=pr.employee_id LEFT JOIN employees r ON r.id=pr.reviewer_id ORDER BY pr.created_at DESC";
$reviews=$pdo->query($sql)->fetchAll();
layout_header();
?>
<?php if(in_array($role,['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'])): ?>
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
  <button onclick="document.getElementById('dlg-create').showModal()" class="btn btn-primary">New Review</button>
</div>
<?php endif; ?>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Employee</th><th>Reviewer</th><th>Period</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($reviews as $rv): ?>
<tr>
<td><?= e($rv['emp_name']) ?></td><td><?= e($rv['reviewer_name']??'-') ?></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($rv['review_period']) ?></td>
<td><?php if($rv['rating']){ for($i=0;$i<5;$i++) echo $i<$rv['rating']?'★':'☆'; } else echo '-'; ?></td>
<td><span class="badge <?= badge_class($rv['status']) ?>"><?= e($rv['status']) ?></span></td>
<td>
<?php
$currentEmpId=null; try{ $s=$pdo->prepare("SELECT id FROM employees WHERE user_id=?"); $s->execute([$_SESSION['user']['id']]); $currentEmpId=$s->fetchColumn(); }catch(Exception $e){}
if($currentEmpId==$rv['employee_id'] && $rv['status']==='SUBMITTED'): ?>
<form method="POST"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($rv['id']) ?>"><button name="acknowledge" class="btn btn-primary btn-sm" style="height:1.5rem;">Acknowledge</button></form>
<?php else: ?><span style="font-size:0.75rem;color:var(--muted-foreground);max-width:12rem;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e(substr($rv['feedback']??'',0,40)) ?></span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($reviews)): ?><tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No reviews</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>

<dialog id="dlg-create" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">New Review</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;">Employee *</label><select name="employee_id" required class="input"><option value="">Select</option><?php foreach($employees as $e): ?><option value="<?= e($e['id']) ?>"><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
<div><label style="font-size:0.8125rem;">Review Period *</label><input name="review_period" required class="input" placeholder="Q1 2024"></div>
<div><label style="font-size:0.8125rem;">Rating (1-5)</label><div style="display:flex;gap:0.25rem;"><?php for($i=1;$i<=5;$i++): ?><label style="cursor:pointer;"><input type="radio" name="rating" value="<?= $i ?>"> <?= $i ?>★</label><?php endfor; ?></div></div>
<div><label style="font-size:0.8125rem;">Goals</label><textarea name="goals" class="input" rows="2"></textarea></div>
<div><label style="font-size:0.8125rem;">Feedback</label><textarea name="feedback" class="input" rows="2"></textarea></div>
<label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;"><input type="checkbox" name="is_draft"> Save as Draft</label>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="create_review" class="btn btn-primary">Create</button></div>
</form>
</dialog>
<?php layout_footer(); ?>
