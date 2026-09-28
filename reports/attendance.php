<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$rows=[];
for($i=6;$i>=0;$i--){ $d=date('Y-m-d', strtotime("-$i days")); $cnt=$pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date=? AND status IN ('PRESENT','LATE')"); $cnt->execute([$d]); $rows[]=['date'=>$d,'count'=>(int)$cnt->fetchColumn()]; }
layout_header('Attendance Report','Last 7 days','brass','<a href="'.base_url('reports/').'" class="btn btn-outline">Back</a>');
?>
<div class="card tab-notch tab-brass"><div class="card-content">
<div style="display:flex;align-items:flex-end;gap:0.5rem;height:10rem;margin-bottom:1rem;">
<?php $max=max(1,max(array_column($rows,'count'))); foreach($rows as $r): $h=($r['count']/$max*100); ?>
<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.25rem;"><div style="width:100%;background:var(--chart-2);border-radius:0.25rem 0.25rem 0 0;height:<?= max(6,$h*0.9) ?>%;min-height:6px;"></div><span style="font-size:0.65rem;color:var(--muted-foreground);"><?= e(date('M d', strtotime($r['date']))) ?></span><span style="font-size:0.7rem;font-weight:600;"><?= (int)$r['count'] ?></span></div>
<?php endforeach; ?>
</div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Present</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= e($r['date']) ?></td><td><?= (int)$r['count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
</div></div>
<?php layout_footer(); ?>
