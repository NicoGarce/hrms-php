<?php
require_once __DIR__.'/../includes/auth.php';
require_login();
require_once __DIR__.'/../includes/layout.php';
$pdo=db();
if(isset($_GET['read'])){ $pdo->prepare("UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=?")->execute([$_GET['read'], $_SESSION['user']['id']]); redirect(base_url('settings/notifications.php')); }
if(isset($_GET['mark_all'])){ $pdo->prepare("UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL")->execute([$_SESSION['user']['id']]); redirect(base_url('settings/notifications.php')); }
$notifs=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50"); $notifs->execute([$_SESSION['user']['id']]); $list=$notifs->fetchAll();
layout_header('Notifications','Your updates','teal','<a href="?mark_all=1" class="btn btn-outline btn-sm">Mark all read</a> <a href="'.base_url('dashboard/').'" class="btn btn-outline btn-sm">Back</a>');
?>
<div style="display:grid;gap:0.5rem;max-width:40rem;">
<?php foreach($list as $n): ?>
<div style="padding:0.75rem;border:1px solid var(--border);border-radius:0.5rem;background:<?= $n['read_at']?'var(--card)':'color-mix(in oklab, var(--primary) 5%, var(--card))' ?>;display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;">
<div><div style="font-weight:600;font-size:0.875rem;"><?= e($n['title']) ?> <?php if(!$n['read_at']): ?><span style="width:0.5rem;height:0.5rem;border-radius:9999px;background:var(--chart-4);display:inline-block;margin-left:0.25rem;"></span><?php endif; ?></div><div style="font-size:0.8125rem;color:var(--muted-foreground);"><?= e($n['message']) ?></div><div style="font-size:0.6875rem;color:var(--muted-foreground);margin-top:0.25rem;"><?= e(date('M d, Y H:i', strtotime($n['created_at']))) ?></div></div>
<?php if(!$n['read_at']): ?><a href="?read=<?= e($n['id']) ?>" class="btn btn-outline btn-sm" style="height:1.5rem;">Mark read</a><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if(empty($list)): ?><div class="card"><div class="card-content" style="text-align:center;color:var(--muted-foreground);">No notifications</div></div><?php endif; ?>
</div>
<?php layout_footer(); ?>
