<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$headcount=(int)$pdo->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL")->fetchColumn();
$present=(int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status IN ('PRESENT','LATE')")->fetchColumn();
$pending=(int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='PENDING'")->fetchColumn();
layout_header('Analytics','Key metrics','slate','<a href="'.base_url('reports/').'" class="btn btn-outline">Back</a>');
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
<div class="card tab-notch tab-teal"><div class="card-content"><div style="font-size:0.75rem;color:var(--muted-foreground);">Headcount</div><div style="font-family:var(--font-mono);font-size:1.75rem;font-weight:700;"><?= $headcount ?></div><div style="height:0.5rem;background:var(--secondary);border-radius:9999px;margin-top:0.5rem;"><div style="width:100%;height:100%;background:var(--chart-1);border-radius:9999px;"></div></div></div></div>
<div class="card tab-notch tab-green"><div class="card-content"><div style="font-size:0.75rem;color:var(--muted-foreground);">Present Today</div><div style="font-family:var(--font-mono);font-size:1.75rem;font-weight:700;"><?= $present ?></div><div style="height:0.5rem;background:var(--secondary);border-radius:9999px;margin-top:0.5rem;"><div style="width:<?= $headcount?round($present/$headcount*100):0 ?>%;height:100%;background:var(--chart-3);border-radius:9999px;"></div></div></div></div>
<div class="card tab-notch tab-brass"><div class="card-content"><div style="font-size:0.75rem;color:var(--muted-foreground);">Pending Leaves</div><div style="font-family:var(--font-mono);font-size:1.75rem;font-weight:700;"><?= $pending ?></div><div style="height:0.5rem;background:var(--secondary);border-radius:9999px;margin-top:0.5rem;"><div style="width:<?= min(100,$pending*20) ?>%;height:100%;background:var(--chart-2);border-radius:9999px;"></div></div></div></div>
</div>
<?php layout_footer(); ?>
