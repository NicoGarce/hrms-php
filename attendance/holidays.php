<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    $name=trim($_POST['name']??''); $date=$_POST['date']??''; $type=$_POST['type']??'PUBLIC';
    if($name&&$date){ try{ $pdo->prepare("INSERT INTO holidays (id,name,date,type) VALUES (?,?,?,?)")->execute([uuid(),$name,$date,$type]); flash('success','Holiday added'); }catch(Exception $e){ flash('error',$e->getMessage()); } }
    redirect(base_url('attendance/holidays.php'));
}
if(isset($_GET['delete'])){ $pdo->prepare("DELETE FROM holidays WHERE id=?")->execute([$_GET['delete']]); flash('success','Holiday deleted'); redirect(base_url('attendance/holidays.php')); }
$holidays=$pdo->query("SELECT * FROM holidays ORDER BY date DESC")->fetchAll();
layout_header('Holidays','Company holidays','teal','<button onclick="document.getElementById(\'dlg\').showModal()" class="btn btn-primary">Add Holiday</button> <a href="'.base_url('attendance/').'" class="btn btn-outline">Back</a>');
?>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Name</th><th>Date</th><th>Type</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($holidays as $h): ?><tr><td><?= e($h['name']) ?></td><td style="font-family:var(--font-mono);"><?= e($h['date']) ?></td><td><span class="badge badge-slate"><?= e($h['type']) ?></span></td><td><a href="?delete=<?= e($h['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="color:var(--destructive);">Delete</a></td></tr><?php endforeach; ?>
<?php if(empty($holidays)): ?><tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No holidays</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>
<dialog id="dlg" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:24rem;width:calc(100% - 2rem);"><form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;"><h3 style="font-weight:600;">Add Holiday</h3><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div><label style="font-size:0.8125rem;">Name *</label><input name="name" required class="input"></div><div><label style="font-size:0.8125rem;">Date *</label><input name="date" type="date" required class="input"></div><div><label style="font-size:0.8125rem;">Type</label><select name="type" class="input"><option>PUBLIC</option><option>COMPANY</option></select></div><div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Add</button></div></form></dialog>
<?php layout_footer(); ?>
