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
        $type=trim($_POST['type']??''); $name=trim($_POST['name']??'');
        $err='';
        if(!$emp) $err='Choose an employee.';
        elseif($type==='') $err='Choose a document type.';
        else {
            // Type must come from the configured catalogue.
            $chk=$pdo->prepare("SELECT name FROM document_types WHERE name=? LIMIT 1"); $chk->execute([$type]);
            if(!$chk->fetch()) $err='Unknown document type.';
        }
        $fileUrl='';
        if($err==='' && isset($_FILES['file']) && $_FILES['file']['error']!==UPLOAD_ERR_NO_FILE){
            $err=validate_upload_file($_FILES['file']);
            if($err===''){
                $ext=strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                $fname=uuid().'.'.$ext;
                $dest=__DIR__.'/../assets/uploads/'.$fname;
                if(!is_dir(dirname($dest))) mkdir(dirname($dest),0777,true);
                move_uploaded_file($_FILES['file']['tmp_name'], $dest);
                $fileUrl=base_url('assets/uploads/'.$fname);
                if(!$name) $name=$_FILES['file']['name'];
            }
        } elseif($err==='') { $fileUrl=trim($_POST['file_url']??''); if(!$name) $name=basename($fileUrl); if(!$fileUrl) $err='Choose a file or paste a URL.'; }
        if($err!==''){ flash('error',$err); redirect(base_url('documents/')); }
        if($emp&&$name&&$fileUrl){ $pdo->prepare("INSERT INTO documents (id,employee_id,type,name,file_url) VALUES (?,?,?,?,?)")->execute([uuid(),$emp,$type,$name,$fileUrl]); audit_log('UPLOAD','documents',null,['type'=>$type]); flash('success','Document uploaded'); }
        redirect(base_url('documents/'));
    }
    // Super Admin / HR Admin can configure the document type dropdown (Part G).
    if(isset($_POST['save_type']) && $isAdmin){
        $tname=trim($_POST['type_name']??''); $mand=isset($_POST['mandatory'])?1:0;
        if($tname!==''){ $pdo->prepare("INSERT INTO document_types (id,name,mandatory) VALUES (?,?,?) ON DUPLICATE KEY UPDATE mandatory=VALUES(mandatory)")->execute([uuid(),$tname,$mand]); audit_log('CREATE','document_types',null,['name'=>$tname]); flash('success','Document type saved'); }
        redirect(base_url('documents/'));
    }
    if(isset($_POST['delete_type']) && $isAdmin){
        $tid=$_POST['type_id']??'';
        // Only delete types with no documents referencing them (by name).
        $t=$pdo->prepare("SELECT name FROM document_types WHERE id=?"); $t->execute([$tid]); $row=$t->fetch();
        if($row){ $c=$pdo->prepare("SELECT COUNT(*) FROM documents WHERE type=?"); $c->execute([$row['name']]); if((int)$c->fetchColumn()>0){ flash('error','Type is in use and cannot be deleted'); } else { $pdo->prepare("DELETE FROM document_types WHERE id=?")->execute([$tid]); audit_log('DELETE','document_types',$tid); flash('success','Document type deleted'); } }
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
$docTypes=all_doc_types();
layout_header('Documents','Uploads are validated (PDF/JPG/PNG/DOC, 5 MB) and served to authorized users only','teal');
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
<td><div style="display:flex;gap:0.25rem;"><a href="<?= base_url('documents/download.php?id='.e($doc['id'])) ?>" class="btn btn-outline btn-sm" style="height:1.5rem;">Download</a><?php if($isAdmin): ?><a href="?delete=<?= e($doc['id']) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);">Delete</a><?php endif; ?></div></td>
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
<div><label style="font-size:0.8125rem;">Type *</label><select name="type" class="input" required><option value="">Select type</option><?php foreach($docTypes as $t): ?><option value="<?= e($t['name']) ?>"><?= e($t['name']) ?><?= $t['mandatory']?' (required for 201)':'' ?></option><?php endforeach; ?></select></div>
<div><label style="font-size:0.8125rem;">Name</label><input name="name" class="input" placeholder="Leave blank to use filename"></div>
<div><label style="font-size:0.8125rem;">File</label><input type="file" name="file" class="input"></div>
<div><label style="font-size:0.8125rem;">Or File URL</label><input name="file_url" class="input" placeholder="https://..."></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="upload" class="btn btn-primary">Upload</button></div>
</form>
</dialog>
<?php if($isAdmin): ?>
<div class="card tab-notch tab-brass" style="margin-top:1rem;"><div class="card-header"><div class="card-title">Document types</div><div class="card-desc">Configures the dropdown above and the 201-file mandatory checklist</div></div>
<div class="card-content" style="display:grid;gap:0.75rem;">
  <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
    <?php foreach($docTypes as $t): ?>
    <form method="POST" style="display:inline-flex;align-items:center;gap:0.35rem;border:1px solid var(--border);border-radius:9999px;padding:0.25rem 0.6rem;font-size:0.75rem;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type_id" value="<?= e($t['id']) ?>">
      <span><?= e($t['name']) ?><?= $t['mandatory']?' • required':'' ?></span>
      <button name="delete_type" class="btn btn-ghost btn-sm" style="height:1.2rem;padding:0 0.3rem;" onclick="return confirmDelete('Delete this document type?')" title="Delete">×</button>
    </form>
    <?php endforeach; ?>
  </div>
  <form method="POST" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div><label style="font-size:0.8125rem;">New type</label><input name="type_name" class="input" placeholder="e.g. Barangay Clearance" required></div>
    <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8125rem;"><input type="checkbox" name="mandatory"> Mandatory for 201</label>
    <button name="save_type" class="btn btn-outline btn-sm">Add type</button>
  </form>
</div></div>
<?php endif; ?>
<?php layout_footer(); ?>
