<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$roles=$pdo->query("SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id=r.id) as user_count FROM roles r ORDER BY r.name")->fetchAll();
layout_header('Roles','Access control','teal','<a href="'.base_url('settings/').'" class="btn btn-outline">Back</a>');
?>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Role</th><th>Description</th><th>Users</th></tr></thead>
<tbody>
<?php foreach($roles as $r): ?><tr><td style="font-weight:500;"><?= e($r['name']) ?></td><td style="font-size:0.8125rem;color:var(--muted-foreground);"><?= e($r['description']??'-') ?></td><td><span class="badge badge-slate"><?= (int)$r['user_count'] ?></span></td></tr><?php endforeach; ?>
</tbody>
</table></div></div></div>
<?php layout_footer(); ?>
