<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$logs=$pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 100")->fetchAll();
layout_header('Audit Logs','System activity','teal','<a href="'.base_url('settings/').'" class="btn btn-outline">Back</a>');
?>
<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Action</th><th>Resource</th><th>Resource ID</th><th>IP</th><th>Date</th></tr></thead>
<tbody>
<?php foreach($logs as $l): ?><tr><td><span class="badge badge-slate"><?= e($l['action']) ?></span></td><td><?= e($l['resource']) ?></td><td style="font-family:var(--font-mono);font-size:0.75rem;"><?= e(substr($l['resource_id']??'-',0,8)) ?></td><td style="font-family:var(--font-mono);font-size:0.75rem;"><?= e($l['ip_address']??'-') ?></td><td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e(date('M d, Y H:i', strtotime($l['created_at']))) ?></td></tr><?php endforeach; ?>
<?php if(empty($logs)): ?><tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No logs</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>
<?php layout_footer(); ?>
