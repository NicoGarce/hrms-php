<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$id=$_GET['id']??''; if(!$id) redirect(base_url('employees/'));
$stmt=$pdo->prepare("SELECT e.*, d.name as dept_name, p.title as pos_title, p.level, u.email, r.name as role_name, ep.phone, ep.address, ep.emergency_contact, ep.date_of_birth, ep.avatar FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id LEFT JOIN users u ON u.id=e.user_id LEFT JOIN roles r ON r.id=u.role_id LEFT JOIN employee_profiles ep ON ep.employee_id=e.id WHERE e.id=? AND e.deleted_at IS NULL");
$stmt->execute([$id]); $emp=$stmt->fetch(); if(!$emp){ flash('error','Employee not found'); redirect(base_url('employees/')); }
// Department Heads may only view their own department (direct URLs included).
$scopeDepts = managed_department_ids();
if ($scopeDepts !== null && !in_array($emp['department_id'], $scopeDepts, true)) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>This employee is outside your department.</p><a href="'.base_url('employees/').'">Back to employees</a>');
}
layout_header($emp['first_name'].' '.$emp['last_name'], 'Employee details', 'teal', (in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']) ? '<a href="'.base_url('employees/edit.php?id='.e($emp['id'])).'" class="btn btn-primary">Edit</a><a href="'.base_url('employees/').'" class="btn btn-outline">Back</a>' : '<a href="'.base_url('employees/').'" class="btn btn-outline">Back</a>'));
$initialsVal = strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
?>
<div style="display:grid;gap:1rem;">
  <div class="card tab-notch tab-teal">
    <div class="card-content" style="display:flex;gap:1rem;align-items:center;">
      <div style="width:3.5rem;height:3.5rem;border-radius:9999px;background:var(--primary);color:var(--primary-foreground);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;"><?= e($initialsVal) ?></div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:1.1rem;"><?= e($emp['first_name'].' '.$emp['last_name']) ?> <span class="badge <?= badge_class($emp['status']) ?>" style="margin-left:0.5rem;"><?= e($emp['status']) ?></span></div>
        <div style="font-size:0.8125rem;color:var(--muted-foreground);"><?= e($emp['email']) ?> • <?= e($emp['employee_code']) ?></div>
        <div style="font-size:0.8125rem;color:var(--muted-foreground);"><?= e($emp['dept_name'] ?? 'No department') ?> • <?= e($emp['pos_title'] ?? 'No position') ?><?= $emp['level'] ? ' ('.e($emp['level']).')' : '' ?></div>
      </div>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;">
    <div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Personal Info</div></div><div class="card-content" style="display:grid;gap:0.6rem;font-size:0.875rem;">
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Employee Code</span><span style="font-family:var(--font-mono);"><?= e($emp['employee_code']) ?></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Email</span><span><?= e($emp['email']) ?></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Department</span><span><?= e($emp['dept_name'] ?? '-') ?></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Position</span><span><?= e($emp['pos_title'] ?? '-') ?></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Level</span><span><?= e($emp['level'] ?? '-') ?></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Hire Date</span><span><?= e(date('M d, Y', strtotime($emp['hire_date']))) ?></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Date of Birth</span><span><?= $emp['date_of_birth'] ? e(date('M d, Y', strtotime($emp['date_of_birth']))) : '-' ?></span></div>
      <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted-foreground);">Role</span><span><?= e($emp['role_name']) ?></span></div>
    </div></div>
    <div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Contact & Address</div></div><div class="card-content" style="display:grid;gap:0.6rem;font-size:0.875rem;">
      <div><div style="color:var(--muted-foreground);font-size:0.75rem;">Phone</div><div><?= e($emp['phone'] ?? '-') ?></div></div>
      <div><div style="color:var(--muted-foreground);font-size:0.75rem;">Address</div><div><?= e($emp['address'] ?? '-') ?></div></div>
      <div><div style="color:var(--muted-foreground);font-size:0.75rem;">Emergency Contact</div><div><?= e($emp['emergency_contact'] ?? '-') ?></div></div>
    </div></div>
  </div>
  <!-- Documents -->
  <div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Documents</div></div><div class="card-content">
    <?php $docs=$pdo->prepare("SELECT * FROM documents WHERE employee_id=? ORDER BY created_at DESC"); $docs->execute([$emp['id']]); $list=$docs->fetchAll(); ?>
    <?php if(empty($list)): ?><p style="color:var(--muted-foreground);font-size:0.875rem;">No documents</p><?php else: ?>
    <div style="display:grid;gap:0.5rem;"><?php foreach($list as $d): ?><div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;border:1px solid var(--border);border-radius:0.5rem;font-size:0.875rem;"><span><?= e($d['name']) ?> <span class="badge badge-slate" style="margin-left:0.5rem;"><?= e($d['type']) ?></span></span><a href="<?= base_url('documents/download.php?id='.e($d['id'])) ?>" class="btn btn-outline btn-sm">Download</a></div><?php endforeach; ?></div>
    <?php endif; ?>
  </div></div>
  <!-- Education & Experience -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;">
  <div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Educational attainment</div></div><div class="card-content" style="display:grid;gap:0.5rem;font-size:0.875rem;">
    <?php $edu=$pdo->prepare("SELECT * FROM employee_education WHERE employee_id=? ORDER BY created_at DESC"); $edu->execute([$emp['id']]); $eduList=$edu->fetchAll(); ?>
    <?php if(empty($eduList)): ?><span style="color:var(--muted-foreground);">None recorded</span><?php else: foreach($eduList as $ed): ?><div><strong><?= e($ed['school']) ?></strong> <span class="badge badge-slate"><?= e($ed['level']) ?></span><div style="color:var(--muted-foreground);font-size:0.75rem;"><?= e($ed['course'] ?? '') ?><?= $ed['year_graduated'] ? ' • '.e($ed['year_graduated']) : '' ?></div></div><?php endforeach; endif; ?>
  </div></div>
  <div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Career experience</div></div><div class="card-content" style="display:grid;gap:0.5rem;font-size:0.875rem;">
    <?php $exp=$pdo->prepare("SELECT * FROM employee_experience WHERE employee_id=? ORDER BY start_date DESC"); $exp->execute([$emp['id']]); $expList=$exp->fetchAll(); ?>
    <?php if(empty($expList)): ?><span style="color:var(--muted-foreground);">None recorded</span><?php else: foreach($expList as $ex): ?><div><strong><?= e($ex['position']) ?></strong> @ <?= e($ex['company']) ?><div style="color:var(--muted-foreground);font-size:0.75rem;"><?= $ex['start_date'] ? e(date('M Y', strtotime($ex['start_date']))) : '?' ?> – <?= $ex['end_date'] ? e(date('M Y', strtotime($ex['end_date']))) : 'Present' ?></div></div><?php endforeach; endif; ?>
  </div></div>
  </div>
</div>
<?php layout_footer(); ?>
