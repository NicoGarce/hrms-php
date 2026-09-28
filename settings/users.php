<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$users=$pdo->query("SELECT u.*, r.name as role_name, CONCAT(e.first_name,' ',e.last_name) as emp_name FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN employees e ON e.user_id=u.id WHERE u.deleted_at IS NULL ORDER BY u.created_at DESC")->fetchAll();
layout_header('Users','System users','teal','<a href="'.base_url('settings/').'" class="btn btn-outline">Back</a>');
?>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Email</th><th>Employee</th><th>Role</th><th>Created</th></tr></thead>
<tbody>
<?php foreach($users as $u): ?><tr><td style="font-size:0.8125rem;"><?= e($u['email']) ?></td><td><?= e($u['emp_name']??'-') ?></td><td><span class="badge badge-slate"><?= e($u['role_name']) ?></span></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e(date('M d, Y', strtotime($u['created_at']))) ?></td></tr><?php endforeach; ?>
</tbody>
</table></div></div></div>
<?php layout_footer(); ?>
