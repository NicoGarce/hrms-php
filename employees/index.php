<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
if(isset($_GET['delete'])){
    if(!in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'])){ flash('error','Not allowed'); redirect(base_url('employees/')); }
    $id=$_GET['delete'];
    $pdo->prepare("UPDATE employees SET deleted_at=NOW() WHERE id=?")->execute([$id]);
    audit_log('DELETE','employees',$id);
    flash('success','Employee deleted');
    redirect(base_url('employees/'));
}
$search = trim($_GET['q'] ?? '');
$sql = "SELECT e.*, d.name as dept_name, p.title as pos_title, u.email FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN users u ON u.id=e.user_id WHERE e.deleted_at IS NULL";
$params=[];
if($search!==''){ $sql.=" AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ? OR u.email LIKE ?)"; $like="%$search%"; $params=[$like,$like,$like,$like]; }
$sql.=" ORDER BY e.created_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
layout_header('Employees','Manage your workforce','teal','<a href="'.base_url('employees/create.php').'" class="btn btn-primary"><i data-lucide="plus" style="width:0.875rem;height:0.875rem;"></i> Add Employee</a>');
?>
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;">
  <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
    <input name="q" value="<?= e($search) ?>" placeholder="Search employees..." class="input" style="width:16rem;">
    <button class="btn btn-outline">Search</button>
    <?php if($search): ?><a href="<?= base_url('employees/') ?>" class="btn btn-ghost">Clear</a><?php endif; ?>
  </form>
</div>
<div class="card tab-notch tab-teal">
  <div class="card-content" style="padding:0;">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Position</th><th>Status</th><th>Hire Date</th><th>Actions</th></tr></thead>
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
              <?php if(in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'])): ?>
              <a href="<?= base_url('employees/edit.php?id='.e($r['id'])) ?>" class="btn btn-outline btn-sm" style="height:1.5rem;padding:0 0.5rem;">Edit</a>
              <a href="<?= base_url('employees/?delete='.e($r['id'])) ?>" onclick="return confirmDelete()" class="btn btn-ghost btn-sm" style="height:1.5rem;padding:0 0.5rem;color:var(--destructive);">Delete</a>
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
