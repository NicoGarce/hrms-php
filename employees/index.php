<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']);
$pdo=db();
$canManage = in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);

if(isset($_GET['delete'])){
    if(!$canManage){ flash('error','Not allowed'); redirect(base_url('employees/')); }
    if(!verify_csrf_get()){ flash('error','Invalid token'); redirect(base_url('employees/')); }
    $id=$_GET['delete'];
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE employees SET deleted_at=NOW() WHERE id=?")->execute([$id]);
        // Deactivate the linked account so a soft-deleted employee cannot log in.
        $pdo->prepare("UPDATE users u JOIN employees e ON e.user_id=u.id SET u.account_state='DEACTIVATED' WHERE e.id=?")->execute([$id]);
        $pdo->commit();
        audit_log('DELETE','employees',$id);
        flash('success','Employee deleted and account deactivated');
    } catch (Exception $e) { $pdo->rollBack(); flash('error','Delete failed'); }
    redirect(base_url('employees/'));
}

$search = trim($_GET['q'] ?? '');
$deptFilter = trim($_GET['dept'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
// Department Heads only see their own department (server-side, always applied).
$scopeDepts = managed_department_ids();
if ($scopeDepts !== null) {
    if (count($scopeDepts) === 1) $deptFilter = $scopeDepts[0]; // lock the filter
}

function employee_where(array &$params): string {
    global $search, $deptFilter, $statusFilter, $scopeDepts;
    $sql = " FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN users u ON u.id=e.user_id WHERE e.deleted_at IS NULL";
    if($search!==''){ $sql.=" AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ? OR u.email LIKE ?)"; $like="%$search%"; $params=[$like,$like,$like,$like]; }
    if($deptFilter!==''){ $sql.=" AND e.department_id=?"; $params[]=$deptFilter; }
    if($statusFilter!==''){ $sql.=" AND e.status=?"; $params[]=$statusFilter; }
    if($scopeDepts!==null){
        if(empty($scopeDepts)) $sql.=" AND 1=0"; // head with no department: sees no one
        else { $sql.=" AND e.department_id IN (" . implode(',', array_fill(0, count($scopeDepts), '?')) . ")"; $params=array_merge($params,$scopeDepts); }
    }
    return $sql;
}

// CSV export (Super Admin / HR Admin only): currently filtered list, no 201/docs.
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
    $params=[];
    $sql = "SELECT e.employee_code, e.first_name, e.last_name, u.email, d.name as dept_name, p.title as pos_title, e.status, e.hire_date" . employee_where($params) . " ORDER BY e.created_at DESC";
    $stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
    audit_log('EXPORT','employees',null,['filters'=>['q'=>$search,'dept'=>$deptFilter,'status'=>$statusFilter],'count'=>count($rows)]);
    $fname = 'employees-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fname.'"');
    $out = fopen('php://output','w');
    fputs($out, "\xEF\xBB\xBF"); // BOM for Excel
    fputcsv($out, ['Employee Number','Name','Email','Department','Position','Status','Hire Date']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['employee_code'], $r['first_name'].' '.$r['last_name'], $r['email'], $r['dept_name'] ?? '', $r['pos_title'] ?? '', $r['status'], $r['hire_date']]);
    }
    fclose($out);
    exit;
}

require_once __DIR__.'/../includes/layout.php';
$params=[];
$sql = "SELECT e.*, d.name as dept_name, p.title as pos_title, u.email" . employee_where($params) . " ORDER BY e.created_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
$depts=$pdo->query("SELECT id,name FROM departments WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$exportQs = http_build_query(array_filter(['q'=>$search,'dept'=>$deptFilter,'status'=>$statusFilter]));
layout_header();
?>
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;justify-content:space-between;">
  <form method="GET" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
    <input name="q" value="<?= e($search) ?>" placeholder="Search number, name, email..." class="input" style="width:16rem;">
    <?php if($scopeDepts !== null): ?>
      <span class="badge badge-blue">Your department only</span>
    <?php else: ?>
    <select name="dept" class="input" style="width:11rem;"><option value="">All Departments</option><?php foreach($depts as $d): ?><option value="<?= e($d['id']) ?>" <?= $deptFilter==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
    <?php endif; ?>
    <select name="status" class="input" style="width:10rem;"><option value="">All Statuses</option><?php foreach(['ACTIVE','ON_LEAVE','PROBATION','TERMINATED','RESIGNED'] as $s): ?><option <?= $statusFilter==$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
    <button class="btn btn-outline">Search</button>
    <?php if($search||$deptFilter||$statusFilter): ?><a href="<?= base_url('employees/') ?>" class="btn btn-ghost">Clear</a><?php endif; ?>
  </form>
  <div style="display:flex;gap:0.5rem;align-items:center;">
    <?php if($canManage): ?>
    <a href="<?= base_url('employees/?export=csv' . ($exportQs ? '&'.$exportQs : '')) ?>" class="btn btn-outline"><i data-lucide="download" style="width:0.875rem;height:0.875rem;"></i> Export CSV</a>
    <a href="<?= base_url('employees/create.php') ?>" class="btn btn-primary"><i data-lucide="plus" style="width:0.875rem;height:0.875rem;"></i> Add Employee</a>
    <?php endif; ?>
  </div>
</div>
<div class="card tab-notch tab-teal">
  <div class="card-content" style="padding:0;">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Employee Number</th><th>Name</th><th>Department</th><th>Position</th><th>Status</th><th>Hire Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($r['employee_code']) ?></td>
            <td><a href="<?= base_url('employees/view.php?id='.e($r['id'])) ?>" style="font-weight:500;color:var(--foreground);text-decoration:none;"><?= e($r['first_name'].' '.$r['last_name']) ?></a><div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e($r['email']) ?></div></td>
            <td><?= e($r['dept_name'] ?? '-') ?></td>
            <td><?= e($r['pos_title'] ?? '-') ?></td>
            <td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
            <td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e(date('M d, Y', strtotime($r['hire_date']))) ?></td>
            <td><div style="display:flex;gap:0.25rem;">
              <a href="<?= base_url('employees/view.php?id='.e($r['id'])) ?>" class="btn btn-ghost btn-sm" style="height:1.5rem;padding:0 0.5rem;">View</a>
              <?php if($canManage): ?>
              <a href="<?= base_url('employees/edit.php?id='.e($r['id'])) ?>" class="btn btn-outline btn-sm" style="height:1.5rem;padding:0 0.5rem;">Edit</a>
              <a href="<?= base_url('employees/?delete='.e($r['id']).'&csrf='.e(csrf_token())) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;padding:0 0.5rem;color:var(--destructive);">Delete</a>
              <?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($rows)): ?><tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No employees found</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php layout_footer(); ?>
