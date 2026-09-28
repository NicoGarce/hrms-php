<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
$byDept=$pdo->query("SELECT d.name as label, COUNT(e.id) as cnt FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.deleted_at IS NULL WHERE d.deleted_at IS NULL GROUP BY d.id")->fetchAll();
$byStatus=$pdo->query("SELECT status as label, COUNT(*) as cnt FROM employees WHERE deleted_at IS NULL GROUP BY status")->fetchAll();
$byPos=$pdo->query("SELECT p.title as label, COUNT(e.id) as cnt FROM positions p LEFT JOIN employees e ON e.position_id=p.id AND e.deleted_at IS NULL WHERE p.deleted_at IS NULL GROUP BY p.id")->fetchAll();
layout_header('Employees Report','Headcount breakdown','teal','<a href="'.base_url('reports/').'" class="btn btn-outline">Back</a> <button onclick="exportCSV()" class="btn btn-primary">Export CSV</button>');
?>
<div style="display:grid;gap:1rem;">
  <div class="card tab-notch tab-teal"><div class="card-header"><div class="card-title">By Department</div></div><div class="card-content"><div class="table-wrap"><table><thead><tr><th>Department</th><th>Count</th><th>Share</th></tr></thead><tbody id="tbl-dept">
  <?php $total=max(1, array_sum(array_column($byDept,'cnt'))); foreach($byDept as $r): $pct=round($r['cnt']/$total*100); ?>
  <tr><td><?= e($r['label']) ?></td><td><?= (int)$r['cnt'] ?></td><td><div style="display:flex;align-items:center;gap:0.5rem;"><div style="flex:1;max-width:10rem;height:0.5rem;background:var(--secondary);border-radius:9999px;overflow:hidden;"><div style="width:<?= $pct ?>%;height:100%;background:var(--chart-1);"></div></div><span style="font-size:0.75rem;"><?= $pct ?>%</span></div></td></tr>
  <?php endforeach; ?></tbody></table></div></div></div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">By Status</div></div><div class="card-content"><div class="table-wrap"><table><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>
    <?php foreach($byStatus as $r): ?><tr><td><span class="badge <?= badge_class($r['label']) ?>"><?= e($r['label']) ?></span></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    <div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">By Position</div></div><div class="card-content"><div class="table-wrap"><table><thead><tr><th>Position</th><th>Count</th></tr></thead><tbody>
    <?php foreach($byPos as $r): ?><tr><td><?= e($r['label']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
  </div>
</div>
<script>
function exportCSV(){
  let csv="Department,Count\n";
  document.querySelectorAll('#tbl-dept tr').forEach(tr=>{ const tds=tr.querySelectorAll('td'); if(tds.length) csv+= tds[0].innerText+","+tds[1].innerText+"\n"; });
  const blob=new Blob([csv],{type:'text/csv'}); const url=URL.createObjectURL(blob); const a=document.createElement('a'); a.href=url; a.download='employees_report.csv'; a.click();
}
</script>
<?php layout_footer(); ?>
