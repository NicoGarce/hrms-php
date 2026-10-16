<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo = db();
maybe_send_onboarding_reminder($_SESSION['user']['id']);
$role = $_SESSION['user']['role'] ?? 'EMPLOYEE';
$isHR = in_array($role, ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'], true);
$isEmployeeOnly = ($role === 'EMPLOYEE');

// HR actions on onboarders: manual reminder / extend deadline (reminder-only).
if($_SERVER['REQUEST_METHOD']==='POST' && verify_csrf() && $isHR){
    if(isset($_POST['remind_onboarder'])){
        $uid=$_POST['user_id']??'';
        $u=$pdo->prepare("SELECT id FROM users WHERE id=? LIMIT 1"); $u->execute([$uid]);
        if($u->fetch()){
            $pdo->prepare("INSERT INTO notifications (id,user_id,type,title,message) VALUES (?,?,?,?,?)")
                ->execute([uuid(),$uid,'manual_reminder','Reminder from HR','HR reminds you to complete your password setup / 201 file. The deadline is a reminder only.']);
            audit_log('REMIND','users',$uid,['by'=>$_SESSION['user']['id']]);
            flash('success','Reminder sent');
        }
        redirect(base_url('dashboard/'));
    }
    if(isset($_POST['extend_deadline'])){
        $eid=$_POST['employee_id']??'';
        $pdo->prepare("UPDATE employee_profiles SET onboarding_deadline=GREATEST(COALESCE(onboarding_deadline,CURDATE()),CURDATE()) + INTERVAL 7 DAY WHERE employee_id=?")->execute([$eid]);
        audit_log('UPDATE','201_file',$eid,['extended'=>'+7 days']);
        flash('success','Deadline extended by 7 days (reminder only)');
        redirect(base_url('dashboard/'));
    }
    // Announcements (Super Admin / HR Admin can create, with memos attached).
    if(isset($_POST['create_announcement'])){
        $title=trim($_POST['title']??''); $content=trim($_POST['content']??'');
        $priority=strtoupper(trim($_POST['priority']??'NORMAL'));
        if(!in_array($priority,['LOW','NORMAL','HIGH'],true)) $priority='NORMAL';
        $expires=trim($_POST['expires_at']??'') ?: null;
        if($title===''||$content===''){ flash('error','Title and content are required'); redirect(base_url('dashboard/')); }
        $memo=null;
        if(isset($_FILES['memo']) && ($_FILES['memo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE){
            $verr=validate_upload_file($_FILES['memo']);
            if($verr!==''){ flash('error',$verr); redirect(base_url('dashboard/')); }
            $ext=strtolower(pathinfo($_FILES['memo']['name'], PATHINFO_EXTENSION));
            $fname=uuid().'.'.$ext;
            $dest=__DIR__.'/../assets/uploads/'.$fname;
            if(!is_dir(dirname($dest))) mkdir(dirname($dest),0777,true);
            move_uploaded_file($_FILES['memo']['tmp_name'], $dest);
            $memo=base_url('assets/uploads/'.$fname);
        }
        $pdo->prepare("INSERT INTO announcements (id,title,content,author_id,priority,memo_file,expires_at) VALUES (?,?,?,?,?,?,?)")
            ->execute([uuid(),$title,$content,$_SESSION['user']['id'],$priority,$memo,$expires]);
        audit_log('CREATE','announcements',null,['title'=>$title]);
        flash('success','Announcement published');
        redirect(base_url('dashboard/'));
    }
    if(isset($_POST['delete_announcement'])){
        $aid=$_POST['id']??'';
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$aid]);
        audit_log('DELETE','announcements',$aid);
        flash('success','Announcement deleted');
        redirect(base_url('dashboard/'));
    }
}
$onboarders = $isHR ? pending_onboarders() : [];

// Stats (managers only; employees see the hero section only).
$totalEmployees = $presentToday = $pendingLeave = $openJobs = 0;
$deptCounts = [];
if(!$isEmployeeOnly){
    $totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL")->fetchColumn();
    $presentToday = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='PRESENT'")->fetchColumn();
    $pendingLeave = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='PENDING'")->fetchColumn();
    $openJobs = $pdo->query("SELECT COUNT(*) FROM recruitment_jobs WHERE status='OPEN'")->fetchColumn();
    $deptCounts = $pdo->query("SELECT d.name as dept, COUNT(e.id) as cnt FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.deleted_at IS NULL WHERE d.deleted_at IS NULL GROUP BY d.id")->fetchAll();
}
$announcements = $pdo->query("SELECT a.*, CONCAT(e.first_name,' ',e.last_name) as author FROM announcements a LEFT JOIN employees e ON e.user_id=a.author_id WHERE a.expires_at IS NULL OR a.expires_at >= NOW() ORDER BY a.created_at DESC LIMIT 5")->fetchAll();
$birthdays = $pdo->query("SELECT e.first_name,e.last_name,e.employee_code,p.date_of_birth FROM employees e JOIN employee_profiles p ON p.employee_id=e.id WHERE p.date_of_birth IS NOT NULL AND e.deleted_at IS NULL LIMIT 3")->fetchAll();

// No Dashboard title / Welcome subtitle / Good-morning greeting — date only.
layout_header();
?>
<div>
  <p style="color:var(--muted-foreground);font-size:0.875rem;margin-bottom:1.25rem;"><?= e(date('l, F j, Y')) ?></p>

  <!-- HERO: Activity & Announcements -->
  <div class="card tab-notch tab-teal" style="margin-bottom:1.25rem;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
      <div><div class="card-title">Activity & Announcements</div><div class="card-desc">Latest updates and memos</div></div>
      <?php if($isHR): ?><button onclick="document.getElementById('dlg-ann').showModal()" class="btn btn-primary btn-sm">New Announcement</button><?php endif; ?>
    </div>
    <div class="card-content" style="display:flex;flex-direction:column;gap:0.75rem;">
      <?php if($announcements): foreach($announcements as $a): ?>
      <div style="display:flex;gap:0.6rem;align-items:flex-start;padding-bottom:0.6rem;border-bottom:1px solid var(--border);">
        <span style="width:0.5rem;height:0.5rem;border-radius:9999px;margin-top:0.4rem;flex-shrink:0;background:<?= $a['priority']=='HIGH'?'var(--chart-4)':($a['priority']=='LOW'?'var(--chart-2)':'var(--chart-1)') ?>;"></span>
        <div style="flex:1;"><div style="font-size:0.875rem;font-weight:600;"><?= e($a['title']) ?></div>
          <div style="font-size:0.8125rem;color:var(--muted-foreground);"><?= nl2br(e($a['content'])) ?></div>
          <div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.2rem;"><?= e($a['author'] ?? 'HR') ?> • <?= e(date('M d, Y', strtotime($a['created_at']))) ?></div>
          <?php if(!empty($a['memo_file'])): ?><div style="margin-top:0.35rem;"><a href="<?= base_url('dashboard/memo.php?id='.e($a['id'])) ?>" class="btn btn-outline btn-sm" style="height:1.6rem;"><i data-lucide="paperclip" style="width:0.75rem;height:0.75rem;"></i> Memo</a></div><?php endif; ?>
        </div>
        <?php if($isHR): ?><form method="POST" style="flex-shrink:0;"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($a['id']) ?>"><button name="delete_announcement" class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);" onclick="return confirmDelete('Delete this announcement?')">Delete</button></form><?php endif; ?>
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

  <?php if($isEmployeeOnly): ?>
    <?php layout_footer(); return; ?>
  <?php endif; ?>

  <!-- Quick actions -->
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
    <a href="<?= base_url('leave/') ?>" class="btn btn-outline" style="border-radius:9999px;"><i data-lucide="plane" style="width:0.875rem;height:0.875rem;"></i> Request Leave</a>
    <?php if($isHR): ?>
    <a href="<?= base_url('employees/create.php') ?>" class="btn btn-outline" style="border-radius:9999px;"><i data-lucide="user-plus" style="width:0.875rem;height:0.875rem;"></i> Add Employee</a>
    <a href="<?= base_url('employees/') ?>" class="btn btn-outline" style="border-radius:9999px;"><i data-lucide="download" style="width:0.875rem;height:0.875rem;"></i> Export Employees CSV</a>
    <?php endif; ?>
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

  <?php if($isHR && $onboarders): ?>
  <!-- Onboarding watchlist (Part C): never-logged-in / incomplete 201, days pending -->
  <div class="card tab-notch tab-brass" style="margin-bottom:1.25rem;">
    <div class="card-header"><div class="card-title">Onboarding Watchlist (<?= count($onboarders) ?>)</div><div class="card-desc">Never logged in or incomplete 201 files — deadline is a reminder only, no auto-deactivation</div></div>
    <div class="card-content" style="padding:0;"><div class="table-wrap"><table>
      <thead><tr><th>Employee</th><th>Number</th><th>State</th><th>Days Pending</th><th>Deadline</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($onboarders as $o): ?>
        <tr>
          <td style="font-weight:500;"><?= e($o['name'] ?? $o['employee_number']) ?><div style="font-size:0.72rem;color:var(--muted-foreground);"><?= $o['last_login'] ? 'Last login '.e(date('M d, Y', strtotime($o['last_login']))) : 'Never logged in' ?></div></td>
          <td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($o['employee_number']) ?></td>
          <td><span class="badge badge-brass"><?= e($o['account_state']) ?></span></td>
          <td><?= (int)$o['days_pending'] ?> day(s)</td>
          <td style="font-size:0.8125rem;"><?= $o['onboarding_deadline'] ? e(date('M d, Y', strtotime($o['onboarding_deadline']))) : '—' ?></td>
          <td><div style="display:flex;gap:0.25rem;flex-wrap:wrap;">
            <form method="POST"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= e($o['user_id']) ?>"><button name="remind_onboarder" class="btn btn-outline btn-sm" style="height:1.5rem;">Remind</button></form>
            <?php if($o['employee_id']): ?><form method="POST"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="employee_id" value="<?= e($o['employee_id']) ?>"><button name="extend_deadline" class="btn btn-ghost btn-sm" style="height:1.5rem;" onclick="return confirm('Extend the 201 deadline by 7 days?')">Extend +7d</button></form><?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
  </div>
  <?php endif; ?>

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
  </div>
</div>

<?php if($isHR): ?>
<dialog id="dlg-ann" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:30rem;width:calc(100% - 2rem);">
<form method="POST" enctype="multipart/form-data" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">New Announcement</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div><label style="font-size:0.8125rem;">Title *</label><input name="title" required class="input"></div>
<div><label style="font-size:0.8125rem;">Content *</label><textarea name="content" required class="input" rows="4"></textarea></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
  <div><label style="font-size:0.8125rem;">Priority</label><select name="priority" class="input"><option>NORMAL</option><option>HIGH</option><option>LOW</option></select></div>
  <div><label style="font-size:0.8125rem;">Expires (optional)</label><input name="expires_at" type="date" class="input"></div>
</div>
<div><label style="font-size:0.8125rem;">Memo attachment (optional — PDF/JPG/PNG/DOC, 5 MB)</label><input type="file" name="memo" class="input"></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button name="create_announcement" class="btn btn-primary">Publish</button></div>
</form>
</dialog>
<?php endif; ?>
<?php layout_footer(); ?>
