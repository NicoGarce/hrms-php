<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$byStatus=$pdo->query("SELECT status, COUNT(*) as cnt FROM leave_requests GROUP BY status")->fetchAll();
$byType=$pdo->query("SELECT lt.name, COUNT(lr.id) as cnt FROM leave_types lt LEFT JOIN leave_requests lr ON lr.leave_type_id=lt.id GROUP BY lt.id")->fetchAll();
layout_header('Leave Report','Leave analytics','green','<a href="'.base_url('reports/').'" class="btn btn-outline">Back</a>');
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
<div class="card tab-notch tab-green"><div class="card-header"><div class="card-title">By Status</div></div><div class="card-content"><div class="table-wrap"><table><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody><?php foreach($byStatus as $r): ?><tr><td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="card tab-notch tab-green"><div class="card-header"><div class="card-title">By Type</div></div><div class="card-content"><div class="table-wrap"><table><thead><tr><th>Type</th><th>Requests</th></tr></thead><tbody><?php foreach($byType as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<?php layout_footer(); ?>
