<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_logged_in(): bool { return isset($_SESSION['user']); }
function require_login(): void {
    if (!is_logged_in()) redirect(base_url('login.php'));
}
function require_role(array $roles): void {
    require_login();
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Your role <b>'.e($role).'</b> cannot access this page.</p><a href="'.base_url('dashboard/').'">Back to dashboard</a>');
    }
}
function can_access(string $href): bool {
    // Hidden sidebar sections are not accessible either
    $section = current_section_for_path((string)(parse_url($href, PHP_URL_PATH) ?: $href));
    if ($section !== null && !in_array($section, sidebar_enabled_sections(), true)) return false;
    $role = $_SESSION['user']['role'] ?? '';
    // Match on the section path regardless of install subfolder
    // (localhost: /hrms-php/dashboard/ — live: /dashboard/).
    // $href is always built via base_url(), so compare path suffixes.
    $map = [
        'dashboard/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE'],
        'employees/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'],
        'departments/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        'positions/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        'recruitment/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        'attendance/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'],
        'leave/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE'],
        'payroll/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        'payslips/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE'],
        'performance/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'],
        'documents/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','EMPLOYEE'],
        'reports/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        'settings/' => ['SUPER_ADMINISTRATOR'],
    ];
    $path = (string)(parse_url($href, PHP_URL_PATH) ?: $href);
    foreach ($map as $section => $roles) {
        if (strpos($path . '/', '/' . $section) !== false) return in_array($role, $roles, true);
    }
    return true;
}
function get_navigation(): array {
    $role = $_SESSION['user']['role'] ?? 'EMPLOYEE';
    $hr   = ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'];
    $all  = [
        ['title'=>'Dashboard','href'=>base_url('dashboard/'),'icon'=>'layout-dashboard','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE']],
        ['title'=>'Employees','href'=>base_url('employees/'),'icon'=>'users','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']],
        ['title'=>'Departments','href'=>base_url('departments/'),'icon'=>'building-2','roles'=>$hr],
        ['title'=>'Positions','href'=>base_url('positions/'),'icon'=>'briefcase','roles'=>$hr],
        ['title'=>'Recruitment','href'=>base_url('recruitment/'),'icon'=>'user-plus','roles'=>$hr,'children'=>[
            ['title'=>'Applicants','href'=>base_url('recruitment/applicants.php')],
        ]],
        ['title'=>'Attendance','href'=>base_url('attendance/'),'icon'=>'calendar-check','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'],'children'=>[
            ['title'=>'Holidays','href'=>base_url('attendance/holidays.php')],
        ]],
        ['title'=>'Leave','href'=>base_url('leave/'),'icon'=>'plane','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE'],'children'=>[
            ['title'=>'Leave Types','href'=>base_url('leave/types.php'),'roles'=>$hr],
            ['title'=>'Calendar','href'=>base_url('leave/calendar.php')],
        ]],
        ['title'=>'Payroll','href'=>base_url('payroll/'),'icon'=>'banknote','roles'=>$hr,'children'=>[
            ['title'=>'Items','href'=>base_url('payroll/items.php')],
        ]],
        ['title'=>'My Payslips','href'=>base_url('payslips/'),'icon'=>'receipt','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE']],
        ['title'=>'Performance','href'=>base_url('performance/'),'icon'=>'trending-up','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']],
        ['title'=>'Documents','href'=>base_url('documents/'),'icon'=>'file-text','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','EMPLOYEE']],
        ['title'=>'Reports','href'=>base_url('reports/'),'icon'=>'bar-chart-3','roles'=>$hr,'children'=>[
            ['title'=>'Employees','href'=>base_url('reports/employees.php')],
            ['title'=>'Attendance','href'=>base_url('reports/attendance.php')],
            ['title'=>'Leave','href'=>base_url('reports/leave.php')],
            ['title'=>'Payroll','href'=>base_url('reports/payroll.php')],
            ['title'=>'Analytics','href'=>base_url('reports/analytics.php')],
        ]],
        ['title'=>'Settings','href'=>base_url('settings/'),'icon'=>'settings','roles'=>['SUPER_ADMINISTRATOR'],'children'=>[
            ['title'=>'Users','href'=>base_url('settings/users.php')],
            ['title'=>'Roles','href'=>base_url('settings/roles.php')],
            ['title'=>'Notifications','href'=>base_url('settings/notifications.php')],
            ['title'=>'Audit Logs','href'=>base_url('settings/audit-logs.php')],
        ]],
        ['title'=>'System Manual','href'=>base_url('system-manual/'),'icon'=>'book-open','roles'=>['SUPER_ADMINISTRATOR']],
    ];
    $nav = [];
    foreach ($all as $item) {
        if (!in_array($role, $item['roles'], true)) continue;
        $item['children'] = array_values(array_filter(
            $item['children'] ?? [],
            fn($c) => !isset($c['roles']) || in_array($role, $c['roles'], true)
        ));
        $nav[] = $item;
    }
    return $nav;
}

/** Sidebar section map: section label => top-level nav titles. */
function nav_section_map(): array {
    return [
        'OVERVIEW' => ['Dashboard'],
        'PEOPLE'   => ['Employees','Departments','Positions','Recruitment'],
        'TIME OFF' => ['Attendance','Leave','Performance'],
        'PAY'      => ['Payroll','My Payslips'],
        'RECORDS'  => ['Documents'],
        'INSIGHTS' => ['Reports'],
        'ADMIN'    => ['Settings','System Manual'],
    ];
}

/** Enabled sidebar sections (ADMIN is always on so settings stay reachable). */
function sidebar_enabled_sections(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $all = array_keys(nav_section_map());
    try {
        $row = db()->query("SELECT sidebar_sections FROM company_settings LIMIT 1")->fetch();
        if (!$row || empty($row['sidebar_sections'])) { $cache = $all; return $cache; }
        $decoded = json_decode((string)$row['sidebar_sections'], true);
        if (!is_array($decoded) || empty($decoded)) { $cache = $all; return $cache; }
        $enabled = array_values(array_intersect($all, $decoded));
        if (empty($enabled)) { $cache = $all; return $cache; }
    } catch (Exception $e) { $cache = $all; return $cache; }
    if (!in_array('ADMIN', $enabled, true)) $enabled[] = 'ADMIN';
    $cache = $enabled;
    return $cache;
}

/** Which sidebar section does a path belong to? Null when it matches no nav item (e.g. profile). */
function current_section_for_path(string $curPath): ?string {
    $map = nav_section_map();
    foreach (get_navigation() as $item) {
        $label = 'GENERAL';
        foreach ($map as $name => $titles) if (in_array($item['title'], $titles, true)) { $label = $name; break; }
        $hrefPath = rtrim((string)parse_url($item['href'], PHP_URL_PATH), '/');
        $match = $hrefPath !== '' && ($curPath === $hrefPath || $curPath === $hrefPath . '/' || strpos($curPath, $hrefPath . '/') === 0);
        if (!$match) {
            foreach ($item['children'] ?? [] as $k) {
                $kid = rtrim((string)parse_url($k['href'], PHP_URL_PATH), '/');
                if ($kid !== '' && ($curPath === $kid || $curPath === $kid . '/')) { $match = true; break; }
            }
        }
        if ($match) return $label;
    }
    return null;
}

/** Group the flat nav into labelled sections for the sidebar. */
function get_navigation_groups(): array {
    $map = nav_section_map();
    $enabled = sidebar_enabled_sections();
    $groups = [];
    foreach (get_navigation() as $item) {
        $label = 'GENERAL';
        foreach ($map as $name => $titles) if (in_array($item['title'], $titles, true)) { $label = $name; break; }
        if (!in_array($label, $enabled, true)) continue;
        $groups[$label][] = $item;
    }
    return $groups;
}
function login_user(string $email, string $password): array {
    $stmt = db()->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=? AND u.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) return [false, 'Invalid email or password'];
    if (!password_verify($password, $user['password'])) return [false, 'Invalid email or password'];
    // fetch employee name if exists
    $stmt2 = db()->prepare("SELECT first_name,last_name FROM employees WHERE user_id=? LIMIT 1");
    $stmt2->execute([$user['id']]);
    $emp = $stmt2->fetch();
    $name = $emp ? ($emp['first_name'].' '.$emp['last_name']) : $user['email'];
    $_SESSION['user'] = [
        'id'=>$user['id'],
        'email'=>$user['email'],
        'role'=>$user['role_name'],
        'role_id'=>$user['role_id'],
        'name'=>$name,
    ];
    // update company theme in session later
    return [true, ''];
}
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
