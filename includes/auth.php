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
    $role = $_SESSION['user']['role'] ?? '';
    $map = [
        '/hrms-php/dashboard/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE'],
        '/hrms-php/employees/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'],
        '/hrms-php/departments/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        '/hrms-php/positions/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        '/hrms-php/recruitment/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        '/hrms-php/attendance/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'],
        '/hrms-php/leave/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE'],
        '/hrms-php/payroll/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        '/hrms-php/payslips/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE'],
        '/hrms-php/performance/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'],
        '/hrms-php/documents/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','EMPLOYEE'],
        '/hrms-php/reports/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR'],
        '/hrms-php/settings/' => ['SUPER_ADMINISTRATOR'],
    ];
    foreach ($map as $prefix => $roles) {
        if (strpos($href, $prefix) === 0) return in_array($role, $roles, true);
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
        ['title'=>'System Manual','href'=>base_url('system-manual/'),'icon'=>'book-open','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD','EMPLOYEE']],
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

/** Group the flat nav into labelled sections for the sidebar. */
function get_navigation_groups(): array {
    $map = [
        'OVERVIEW' => ['Dashboard'],
        'PEOPLE'   => ['Employees','Departments','Positions','Recruitment'],
        'TIME OFF' => ['Attendance','Leave','Performance'],
        'PAY'      => ['Payroll','My Payslips'],
        'RECORDS'  => ['Documents'],
        'INSIGHTS' => ['Reports'],
        'ADMIN'    => ['Settings','System Manual'],
    ];
    $groups = [];
    foreach (get_navigation() as $item) {
        $label = 'GENERAL';
        foreach ($map as $name => $titles) if (in_array($item['title'], $titles, true)) { $label = $name; break; }
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
