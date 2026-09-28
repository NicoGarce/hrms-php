<?php
// layout helpers - call layout_header() at top, layout_footer() at bottom
function layout_header(string $title='', string $accent='none'): void {
    $user = current_user();
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // theme from company_settings or localStorage fallback
    $theme = 'teal';
    try {
        $row = db()->query("SELECT theme FROM company_settings LIMIT 1")->fetch();
        if ($row && $row['theme']) $theme = $row['theme'];
    } catch(Exception $e){}
    $initials = initials($user['name'] ?? $user['email'] ?? 'U');
    $onProfile = nav_is_active(base_url('profile/'), parse_url($uri, PHP_URL_PATH) ?: '/');
    $flashSuccess = flash('success');
    $flashError = flash('error');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme==='teal'?'':e($theme) ?>" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?: 'HRMS') ?> — HRMS</title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full antialiased">
<div class="app-shell">
  <!-- Sidebar desktop -->
  <aside id="sidebar">
    <div class="sb-brand">
      <div class="sb-logo">H</div>
      <span class="sb-brand-name">HRMS</span>
    </div>
    <?php render_sidebar('desktop', parse_url($uri, PHP_URL_PATH) ?: '/', $user, $initials); ?>
    <div class="sb-footer">
      <button id="btn-collapse" class="sb-link sb-collapse" type="button" title="Collapse sidebar">
        <i data-lucide="panel-left-close" class="sb-icon sb-collapse-icon-close"></i>
        <i data-lucide="panel-left-open" class="sb-icon sb-collapse-icon-open"></i>
        <span class="sb-label sb-collapse-text">Collapse</span>
      </button>
    </div>
  </aside>

  <!-- Mobile drawer -->
  <div id="mobile-drawer" class="hidden">
    <div id="drawer-overlay"></div>
    <div class="drawer-panel">
      <div class="sb-brand">
        <div class="sb-logo">H</div>
        <span class="sb-brand-name">HRMS</span>
        <button class="btn btn-ghost btn-icon sb-drawer-close" data-close type="button" title="Close menu" style="margin-left:auto;color:var(--sidebar-foreground);">
          <i data-lucide="x" style="width:1rem;height:1rem;"></i>
        </button>
      </div>
      <?php render_sidebar('drawer', parse_url($uri, PHP_URL_PATH) ?: '/', $user, $initials); ?>
    </div>
  </div>


  <div class="main-col">
    <header class="topbar">
      <button id="btn-mobile-menu" class="btn btn-ghost btn-icon" style="flex-shrink:0;" aria-label="Open menu" aria-controls="mobile-drawer"><i data-lucide="menu" style="width:1.25rem;height:1.25rem;"></i></button>
      <div style="flex:1;display:flex;align-items:center;gap:0.75rem;">
        <button id="btn-search" class="btn btn-outline" style="height:2rem;gap:0.5rem;color:var(--muted-foreground);background:var(--muted);border-color:var(--border);">
          <i data-lucide="search" style="width:0.875rem;height:0.875rem;"></i> Search...
          <kbd class="search-kbd">⌘K</kbd>
        </button>
      </div>
      <div style="display:flex;align-items:center;gap:0.5rem;">
        <button id="btn-theme" class="btn btn-ghost btn-icon" title="Toggle theme"><i data-lucide="sun" style="width:1.1rem;height:1.1rem;"></i></button>
        <div style="position:relative;">
          <a href="<?= base_url('settings/notifications.php') ?>" class="btn btn-ghost btn-icon" title="Notifications"><i data-lucide="bell" style="width:1.1rem;height:1.1rem;"></i></a>
        </div>
        <div style="position:relative;display:flex;align-items:center;gap:0.5rem;">
          <div style="width:2rem;height:2rem;border-radius:9999px;background:var(--primary);color:var(--primary-foreground);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:600;"><?= e($initials) ?></div>
          <div class="topbar-user-meta">
            <span style="font-size:0.8125rem;font-weight:500;"><?= e($user['name'] ?? $user['email']) ?></span>
            <span style="font-size:0.6875rem;color:var(--muted-foreground);"><?= e($user['role'] ?? '') ?></span>
          </div>
          <div style="display:flex;gap:0.25rem;margin-left:0.25rem;">
            <a href="<?= base_url('profile/') ?>" class="btn btn-ghost btn-icon<?= $onProfile ? ' topbar-link-active' : '' ?>" title="Profile"<?= $onProfile ? ' aria-current="page"' : '' ?>><i data-lucide="user" style="width:1rem;height:1rem;"></i></a>
            <a href="<?= base_url('logout.php') ?>" class="btn btn-ghost btn-icon" title="Logout"><i data-lucide="log-out" style="width:1rem;height:1rem;"></i></a>
          </div>
        </div>
      </div>
    </header>
    <main class="content animate-fade-in">
      <?php if($flashSuccess): ?><div class="toast" style="border-left:3px solid var(--chart-3);"><?= e($flashSuccess) ?></div><?php endif; ?>
      <?php if($flashError): ?><div class="toast" style="border-left:3px solid var(--destructive);"><?= e($flashError) ?></div><?php endif; ?>
<?php
}

function layout_footer(): void {
?>
    </main>
  </div>
</div>
<!-- Command palette -->
<div id="cmd-palette" class="hidden" style="position:fixed;inset:0;z-index:60;display:flex;align-items:flex-start;justify-content:center;padding-top:10vh;background:oklch(0 0 0 / 0.3);backdrop-filter:blur(4px);">
  <div style="width:100%;max-width:32rem;background:var(--card);border:1px solid var(--border);border-radius:0.75rem;box-shadow:0 16px 48px oklch(0 0 0 / 0.2);overflow:hidden;">
    <div style="display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1rem;border-bottom:1px solid var(--border);">
      <i data-lucide="search" style="width:1rem;height:1rem;color:var(--muted-foreground);"></i>
      <input id="cmd-input" placeholder="Search employees, departments..." style="flex:1;border:none;outline:none;background:transparent;font-size:0.875rem;color:var(--foreground);">
      <button data-close class="btn btn-ghost btn-icon" style="width:1.5rem;height:1.5rem;"><i data-lucide="x" style="width:0.875rem;height:0.875rem;"></i></button>
    </div>
    <div id="cmd-results" style="max-height:20rem;overflow:auto;padding:0.5rem;">
      <div style="padding:0.5rem;font-size:0.8125rem;color:var(--muted-foreground);">Type to search... (press Esc to close)</div>
      <div style="display:grid;gap:0.25rem;margin-top:0.5rem;">
        <a href="<?= base_url('employees/') ?>" class="btn btn-ghost" style="justify-content:flex-start;">👥 Employees</a>
        <a href="<?= base_url('departments/') ?>" class="btn btn-ghost" style="justify-content:flex-start;">🏢 Departments</a>
        <a href="<?= base_url('attendance/') ?>" class="btn btn-ghost" style="justify-content:flex-start;">📅 Attendance</a>
        <a href="<?= base_url('leave/') ?>" class="btn btn-ghost" style="justify-content:flex-start;">✈️ Leave</a>
        <a href="<?= base_url('payroll/') ?>" class="btn btn-ghost" style="justify-content:flex-start;">💰 Payroll</a>
      </div>
    </div>
  </div>
</div>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script>lucide.createIcons();</script>
<script>
// command search live
document.getElementById('cmd-input')?.addEventListener('input', async (e)=>{
  const q=e.target.value.trim();
  const res=document.getElementById('cmd-results');
  if(!q){res.innerHTML='<div style="padding:0.5rem;font-size:0.8125rem;color:var(--muted-foreground);">Type to search...</div>';return;}
  try{
    const r=await fetch('<?= base_url('api/search.php') ?>?q='+encodeURIComponent(q));
    const data=await r.json();
    let html='';
    if(data.employees?.length){ html+='<div style="font-size:0.7rem;font-weight:600;color:var(--muted-foreground);padding:0.5rem 0.5rem 0;">Employees</div>'; data.employees.forEach(emp=>{ html+=`<a href="<?= base_url('employees/view.php?id=') ?>${emp.id}" class="btn btn-ghost" style="justify-content:flex-start;width:100%;">${emp.name} <span style="color:var(--muted-foreground);margin-left:0.5rem;">${emp.code}</span></a>`; }); }
    if(!html) html='<div style="padding:0.75rem;color:var(--muted-foreground);font-size:0.875rem;">No results</div>';
    res.innerHTML=html;
  }catch(err){ res.innerHTML='<div style="padding:0.75rem;color:var(--destructive);">Search failed</div>'; }
});
</script>
</body>
</html>
<?php
}
function page_header(string $title, string $subtitle='', string $accent='teal', string $actionHtml=''): void {
    echo '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">';
    echo '<div><h1 style="font-family:var(--font-heading);font-size:1.5rem;font-weight:700;line-height:1.2;">'.e($title).'</h1>';
    if($subtitle) echo '<p style="color:var(--muted-foreground);font-size:0.875rem;margin-top:0.25rem;">'.e($subtitle).'</p>';
    echo '</div>';
    if($actionHtml) echo '<div style="display:flex;gap:0.5rem;align-items:center;">'.$actionHtml.'</div>';
    echo '</div>';
}
function card_start(string $title='', string $desc='', string $accent='none'): void {
    $cls = 'card tab-notch';
    if($accent && $accent!=='none') $cls .= ' tab-'.$accent;
    echo '<div class="'.$cls.'">';
    if($title || $desc){
        echo '<div class="card-header">';
        if($title) echo '<div class="card-title">'.e($title).'</div>';
        if($desc) echo '<div class="card-desc">'.e($desc).'</div>';
        echo '</div>';
    }
    echo '<div class="card-content">';
}
function card_end(): void { echo '</div></div>'; }

/** Is $href the current page, or an ancestor of it? */
function nav_is_active(string $href, string $curPath): bool {
    $path = rtrim((string)parse_url($href, PHP_URL_PATH), '/');
    if ($path === '') return false;
    if ($curPath === $path || $curPath === $path.'/') return true;
    return strpos($curPath, $path.'/') === 0;
}

/** Sub-links match on the exact path only, so siblings never double-highlight. */
function nav_is_exact(string $href, string $curPath): bool {
    $path = rtrim((string)parse_url($href, PHP_URL_PATH), '/');
    return $path !== '' && ($curPath === $path || $curPath === $path.'/');
}

/**
 * Renders the navigation tree. Used for both the desktop rail and the
 * mobile drawer so the two can never drift apart.
 */
function render_sidebar(string $variant, string $curPath, ?array $user, string $initials): void {
    $groups = get_navigation_groups();
    echo '<nav class="sb-nav">';
    foreach ($groups as $label => $items) {
        echo '<div class="sb-group"><div class="sb-group-label">'.e($label).'</div>';
        foreach ($items as $item) {
            $kids    = $item['children'] ?? [];
            $active  = nav_is_active($item['href'], $curPath);
            $subOpen = false;
            foreach ($kids as $k) if (nav_is_exact($k['href'], $curPath)) $subOpen = true;
            $listId  = 'sb-sub-'.$variant.'-'.preg_replace('/[^a-z0-9]+/i', '-', $item['title']);
            echo '<div class="sb-row">';
            echo '<a href="'.e($item['href']).'" class="sb-link'.($active ? ' active' : '').'"'.($active ? ' aria-current="page"' : '').' title="'.e($item['title']).'">';
            echo '<i data-lucide="'.e($item['icon']).'" class="sb-icon"></i>';
            echo '<span class="sb-label">'.e($item['title']).'</span></a>';
            if ($kids) {
                echo '<button class="sb-toggle'.($subOpen ? '' : ' is-collapsed').'" type="button" data-sb-toggle="'.$listId.'" aria-expanded="'.($subOpen ? 'true' : 'false').'" aria-controls="'.$listId.'" title="'.e($item['title']).'">';
                echo '<i data-lucide="chevron-down"></i></button>';
            }
            echo '</div>';
            if ($kids) {
                echo '<div class="sb-sub'.($subOpen ? '' : ' is-collapsed').'" id="'.$listId.'">';
                foreach ($kids as $k) {
                    $kActive = nav_is_exact($k['href'], $curPath);
                    echo '<a href="'.e($k['href']).'" class="sb-sub-link'.($kActive ? ' active' : '').'"'.($kActive ? ' aria-current="page"' : '').' title="'.e($item['title']).' &middot; '.e($k['title']).'"><span class="sb-label">'.e($k['title']).'</span></a>';
                }
                echo '</div>';
            }
        }
        echo '</div>';
    }
    echo '</nav>';

    $name = (string)($user['name'] ?? $user['email'] ?? '');
    echo '<div class="sb-footer sb-user-row">';
    echo '<div class="sb-user"><div class="sb-avatar">'.e($initials).'</div>';
    echo '<div class="sb-user-meta"><div class="sb-user-name">'.e($name).'</div>';
    echo '<div class="sb-user-role">'.e((string)($user['role'] ?? '')).'</div></div></div>';
    if ($variant === 'drawer') {
        echo '<a href="'.base_url('profile/').'" class="sb-link sb-sub-link-flat"><i data-lucide="user" class="sb-icon"></i><span class="sb-label">My Profile</span></a>';
        echo '<a href="'.base_url('logout.php').'" class="sb-link sb-sub-link-flat"><i data-lucide="log-out" class="sb-icon"></i><span class="sb-label">Sign out</span></a>';
    }
    echo '</div>';
}
