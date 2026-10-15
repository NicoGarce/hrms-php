<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$currentEmp=null; try{ $s=$pdo->prepare("SELECT id FROM employees WHERE user_id=? LIMIT 1"); $s->execute([$_SESSION['user']['id']]); $currentEmp=$s->fetchColumn(); }catch(Exception $e){}
$isAdmin=in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf()){
    if(isset($_POST['upload'])){
        $emp = $_POST['employee_id'] ?? $currentEmp;
        if(!$isAdmin) $emp=$currentEmp;
        $type=trim($_POST['type']??'general'); $name=trim($_POST['name']??'');
        $fileUrl='';
        if(isset($_FILES['file']) && $_FILES['file']['error']===UPLOAD_ERR_OK){
            $ext=pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $fname=uuid().'.'.$ext;
            $dest=__DIR__.'/../assets/uploads/'.$fname;
            if(!is_dir(dirname($dest))) mkdir(dirname($dest),0777,true);
            move_uploaded_file($_FILES['file']['tmp_name'], $dest);
            $fileUrl=base_url('assets/uploads/'.$fname);
            if(!$name) $name=$_FILES['file']['name'];
        } else { $fileUrl=trim($_POST['file_url']??''); if(!$name) $name=basename($fileUrl); }
        if($emp&&$name&&$fileUrl){ $pdo->prepare("INSERT INTO documents (id,employee_id,type,name,file_url) VALUES (?,?,?,?,?)")->execute([uuid(),$emp,$type,$name,$fileUrl]); flash('success','Document uploaded'); }
        redirect(base_url('documents/'));
    }
}
if(isset($_GET['delete']) && $isAdmin){ $pdo->prepare("DELETE FROM documents WHERE id=?")->execute([$_GET['delete']]); flash('success','Document deleted'); redirect(base_url('documents/')); }
$filterType=trim($_GET['type']??'');
$sql="SELECT d.*, CONCAT(e.first_name,' ',e.last_name) as emp_name FROM documents d JOIN employees e ON e.id=d.employee_id WHERE 1=1"; $params=[];
if(!$isAdmin && $currentEmp){ $sql.=" AND d.employee_id=?"; $params[]=$currentEmp; }
if($filterType){ $sql.=" AND d.type LIKE ?"; $params[]="%$filterType%"; }
$sql.=" ORDER BY d.created_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $docs=$stmt->fetchAll();
$employees=$pdo->query("SELECT e.id, CONCAT(e.first_name,' ',e.last_name) as name FROM employees e WHERE e.deleted_at IS NULL ORDER BY e.first_name")->fetchAll();
layout_header();
?>
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;justify-content:space-between;">
<form method="GET" style="display:flex;gap:0.5rem;"><input name="type" value="<?= e($filterType) ?>" placeholder="Filter by type..." class="input" style="width:14rem;"><button class="btn btn-outline btn-sm">Filter</button><?php if($filterType): ?><a href="<?= base_url('documents/') ?>" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?></form>
<div style="display:flex;gap:0.5rem;align-items:center;">
<button onclick="document.getElementById('dlg-upload').showModal()" class="btn btn-primary">Upload</button>
</div>
</div>
<div class="card tab-notch tab-slate"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Name</th><th>Employee</th><th>Type</th><th>Uploaded</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($docs as $doc): ?>
<tr>
<td style="display:flex;align-items:center;gap:0.5rem;font-weight:500;"><i data-lucide="file-text" style="width:0.875rem;height:0.875rem;color:var(--chart-5);"></i><?= e($doc['name']) ?></td>
<td><?= e($doc['emp_name']) ?></td><td><span class="badge badge-slate"><?= e($doc['type']) ?></span></td>
<td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e(date('M d, Y', strtotime($doc['uploaded_at']))) ?></td>
<td><div style="display:flex;gap:0.25rem;"><a href="<?= e($doc['file_url']) ?>" target="_blank" class="btn btn-outline btn-sm" style="height:1.5rem;">Download</a><?php if($isAdmin): ?><a href="?delete=<?= e($doc['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);">Delete</a><?php endif; ?></div></td>
</tr>
<?php endforeach; ?>
<?php if(empty($docs)): ?><tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No documents</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>

<dialog id="dlg-upload" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:28rem;width:calc(100% - 2rem);">
<form method="POST" enctype="multipart/form-data" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Upload Document</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<?php if($isAdmin): ?><div><label style="font-size:0.8125rem;">Employee *</label><select name="employee_id" class="input"><option value="">Select</option><?php foreach($employees as $e): ?><option value="<?= e($e['id']) ?>"><?= e($e['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
<div><label style="font-size:0.8125rem;">Type</label><input name="type" class="input" placeholder="contract, certificate..."></div>
<div><label style="font-size:0.8125rem;">Name</label><input name="name" class="input" placeholder="Leave blank to use filename"></div>
<div><label style="font-size:0.8125rem;">File</label><input type="file" name="file" class="input"></div>
<div><label style="font-size:0.8125rem;">Or File URL</label><input name="file_url" class="input" placeholder="https://..."></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="upload" class="btn btn-primary">Upload</button></div>
</form>
</dialog>
<?php layout_footer(); ?>
