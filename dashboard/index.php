<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo = db();
// Stats
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL")->fetchColumn();
$presentToday = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='PRESENT'")->fetchColumn();
$pendingLeave = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='PENDING'")->fetchColumn();
$openJobs = $pdo->query("SELECT COUNT(*) FROM recruitment_jobs WHERE status='OPEN'")->fetchColumn();
$announcements = $pdo->query("SELECT * FROM announcements WHERE expires_at IS NULL OR expires_at >= NOW() ORDER BY created_at DESC LIMIT 3")->fetchAll();
$birthdays = $pdo->query("SELECT e.first_name,e.last_name,e.employee_code,p.date_of_birth FROM employees e JOIN employee_profiles p ON p.employee_id=e.id WHERE p.date_of_birth IS NOT NULL AND e.deleted_at IS NULL LIMIT 3")->fetchAll();
// Dept distribution
$deptCounts = $pdo->query("SELECT d.name as dept, COUNT(e.id) as cnt FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.deleted_at IS NULL WHERE d.deleted_at IS NULL GROUP BY d.id")->fetchAll();
layout_header('Dashboard','Welcome back, '.($_SESSION['user']['name'] ?? ''));
?>
<div>
  <div style="margin-bottom:1.5rem;">
    <h1 style="font-family:var(--font-heading);font-size:1.5rem;font-weight:700;"><?= e(greeting()) ?>, <?= e(explode(' ', $_SESSION['user']['name'] ?? 'there')[0]) ?>!</h1>
    <p style="color:var(--muted-foreground);font-size:0.875rem;"><?= e(date('l, F j, Y')) ?> — Here's what's happening today.</p>
  </div>

  <!-- Quick actions -->
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
    <a href="<?= base_url('leave/') ?>" class="btn btn-outline" style="border-radius:9999px;"><i data-lucide="plane" style="width:0.875rem;height:0.875rem;"></i> Request Leave</a>
    <?php if(in_array($_SESSION['user']['role'], ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'])): ?>
    <a href="<?= base_url('employees/create.php') ?>" class="btn btn-outline" style="border-radius:9999px;"><i data-lucide="user-plus" style="width:0.875rem;height:0.875rem;"></i> Add Employee</a>
    <?php endif; ?>
    <a href="<?= base_url('reports/') ?>" class="btn btn-outline" style="border-radius:9999px;"><i data-lucide="bar-chart-3" style="width:0.875rem;height:0.875rem;"></i> Generate Report</a>
  </div>

  <!-- Stats grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.25rem;">
    <div class="card tab-notch tab-teal"><div class="card-content" style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div><div style="font-size:0.75rem;color:var(--muted-foreground);font-weight:500;">Total Employees</div><div style="font-family:var(--font-mono);font-size:1.75rem;font-weight:700;"><?= (int)$totalEmployees ?></div><div style="font-size:0.75rem;color:var(--chart-3);">+2.5% from last month</div></div>
      <div style="width:2.25rem;height:2.25rem;border-radius:0.5rem;background:color-mix(in oklab, var(--chart-1) 15%, transparent);display:flex;align-items:center;justify-content:center;color:var(--chart-1);"><i data-lucide="users" style="width:1.1rem;height:1.1rem;"></i></div>
    </div></div>
    <div class="card tab-notch tab-green"><div class="card-content" style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div><div style="font-size:0.75rem;color:var(--muted-foreground);font-weight:500;">Present Today</div><div style="font-family:var(--font-mono);font-size:1.75rem;font-weight:700;"><?= (int)$presentToday ?></div><div style="font-size:0.75rem;color:var(--chart-3);">On time</div></div>
      <div style="width:2.25rem;height:2.25rem;border-radius:0.5rem;background:color-mix(in oklab, var(--chart-3) 15%, transparent);display:flex;align-items:center;justify-content:center;color:var(--chart-3);"><i data-lucide="calendar-check" style="width:1.1rem;height:1.1rem;"></i></div>
    </div></div>
    <div class="card tab-notch tab-brass"><div class="card-content" style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div><div style="font-size:0.75rem;color:var(--muted-foreground);font-weight:500;">Pending Leaves</div><div style="font-family:var(--font-mono);font-size:1.75rem;font-weight:700;"><?= (int)$pendingLeave ?></div><div style="font-size:0.75rem;color:var(--chart-2);">Needs review</div></div>
      <div style="width:2.25rem;height:2.25rem;border-radius:0.5rem;background:color-mix(in oklab, var(--chart-2) 15%, transparent);display:flex;align-items:center;justify-content:center;color:var(--chart-2);"><i data-lucide="plane" style="width:1.1rem;height:1.1rem;"></i></div>
    </div></div>
    <div class="card tab-notch tab-brick"><div class="card-content" style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div><div style="font-size:0.75rem;color:var(--muted-foreground);font-weight:500;">Open Positions</div><div style="font-family:var(--font-mono);font-size:1.75rem;font-weight:700;"><?= (int)$openJobs ?></div><div style="font-size:0.75rem;color:var(--chart-4);">Hiring</div></div>
      <div style="width:2.25rem;height:2.25rem;border-radius:0.5rem;background:color-mix(in oklab, var(--chart-4) 15%, transparent);display:flex;align-items:center;justify-content:center;color:var(--chart-4);"><i data-lucide="briefcase" style="width:1.1rem;height:1.1rem;"></i></div>
    </div></div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;">
    <!-- Attendance sparkline (CSS bars) -->
    <div class="card tab-notch tab-slate">
      <div class="card-header"><div class="card-title">Attendance Trend (7 days)</div><div class="card-desc">Daily present count</div></div>
      <div class="card-content">
        <?php
        $trend = [];
        for($i=6;$i>=0;$i--){ $d=date('Y-m-d', strtotime("-$i days")); $c=$pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date=? AND status IN ('PRESENT','LATE')"); $c->execute([$d]); $trend[]=['date'=>date('M d', strtotime($d)), 'count'=>(int)$c->fetchColumn()]; }
        $max = max(1, max(array_column($trend,'count')));
        ?>
        <div style="display:flex;align-items:flex-end;gap:0.4rem;height:6rem;">
          <?php foreach($trend as $t): $h = $max ? ($t['count']/$max*100) : 0; ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.25rem;">
            <div style="width:100%;background:var(--chart-1);border-radius:0.25rem 0.25rem 0 0;height:<?= max(4,$h*0.8) ?>px;opacity:0.85;"></div>
            <span style="font-size:0.65rem;color:var(--muted-foreground);"><?= e($t['date']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:0.5rem;font-size:0.75rem;color:var(--muted-foreground);">Counts from attendance records</div>
      </div>
    </div>

    <!-- Dept donut (bars) -->
    <div class="card tab-notch tab-teal">
      <div class="card-header"><div class="card-title">Employees by Department</div><div class="card-desc">Distribution</div></div>
      <div class="card-content">
        <?php $colors=['var(--chart-1)','var(--chart-2)','var(--chart-3)','var(--chart-4)','var(--chart-5)']; $total=max(1,array_sum(array_column($deptCounts,'cnt'))); ?>
        <?php foreach($deptCounts as $idx=>$dc): $pct = round($dc['cnt']/$total*100); ?>
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.6rem;">
          <span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:<?= $colors[$idx%count($colors)] ?>;flex-shrink:0;"></span>
          <span style="flex:1;font-size:0.8125rem;"><?= e($dc['dept']) ?></span>
          <div style="flex:1;max-width:10rem;height:0.5rem;background:var(--secondary);border-radius:9999px;overflow:hidden;"><div style="height:100%;width:<?= $pct ?>%;background:<?= $colors[$idx%count($colors)] ?>;"></div></div>
          <span style="font-size:0.75rem;font-family:var(--font-mono);min-width:2.5rem;text-align:right;"><?= (int)$dc['cnt'] ?> (<?= $pct ?>%)</span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($deptCounts)): ?><p style="color:var(--muted-foreground);font-size:0.875rem;">No departments</p><?php endif; ?>
      </div>
    </div>

    <!-- Activity feed -->
    <div class="card tab-notch tab-slate" style="grid-column:span 1;">
      <div class="card-header"><div class="card-title">Activity & Announcements</div></div>
      <div class="card-content" style="display:flex;flex-direction:column;gap:0.75rem;">
        <?php if($announcements): foreach($announcements as $a): ?>
        <div style="display:flex;gap:0.6rem;align-items:flex-start;padding-bottom:0.6rem;border-bottom:1px solid var(--border);">
          <span style="width:0.5rem;height:0.5rem;border-radius:9999px;margin-top:0.4rem;flex-shrink:0;background:<?= $a['priority']=='HIGH'?'var(--chart-4)':($a['priority']=='LOW'?'var(--chart-2)':'var(--chart-1)') ?>;"></span>
          <div><div style="font-size:0.8125rem;font-weight:600;"><?= e($a['title']) ?></div><div style="font-size:0.75rem;color:var(--muted-foreground);display: -webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= e(substr($a['content'],0,120)) ?></div><div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.2rem;"><?= e(date('M d', strtotime($a['created_at']))) ?></div></div>
        </div>
        <?php endforeach; else: ?><p style="color:var(--muted-foreground);font-size:0.875rem;">No announcements</p><?php endif; ?>
        <div style="margin-top:0.25rem;">
          <div style="font-size:0.75rem;font-weight:600;margin-bottom:0.5rem;">Birthdays</div>
          <?php foreach($birthdays as $b): ?>
          <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">
            <div style="width:1.75rem;height:1.75rem;border-radius:9999px;background:var(--primary);color:var(--primary-foreground);display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:600;"><?= e(initials($b['first_name'].' '.$b['last_name'])) ?></div>
            <span style="font-size:0.8125rem;"><?= e($b['first_name'].' '.$b['last_name']) ?></span>
            <span style="font-size:0.6875rem;color:var(--muted-foreground);"><?= e(date('M d', strtotime($b['date_of_birth']))) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php layout_footer(); ?>
