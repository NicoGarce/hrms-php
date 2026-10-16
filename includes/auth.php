<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_logged_in(): bool { return isset($_SESSION['user']); }
function require_login(): void {
    if (!is_logged_in()) redirect(base_url('login.php'));
    enforce_account_state();
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
        'performance/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD'],
        'documents/' => ['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','EMPLOYEE'],
        'user-management/' => ['SUPER_ADMINISTRATOR'],
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
        ['title'=>'Performance','href'=>base_url('performance/'),'icon'=>'trending-up','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','DEPARTMENT_HEAD']],
        ['title'=>'Documents','href'=>base_url('documents/'),'icon'=>'file-text','roles'=>['SUPER_ADMINISTRATOR','HR_ADMINISTRATOR','EMPLOYEE']],
        ['title'=>'User Management','href'=>base_url('user-management/'),'icon'=>'shield-check','roles'=>['SUPER_ADMINISTRATOR']],
        ['title'=>'Settings','href'=>base_url('settings/'),'icon'=>'settings','roles'=>['SUPER_ADMINISTRATOR'],'children'=>[
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
        'RECORDS'  => ['Documents'],
        'ADMIN'    => ['User Management','Settings','System Manual'],
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
/**
 * Department scope for the current user. Returns null for unrestricted roles
 * (full access) or an array of department ids a Department Head may view.
 * A head's department is their own employee record's department, since
 * departments.head_id is not maintained by the UI.
 */
function managed_department_ids(): ?array {
    if (($_SESSION['user']['role'] ?? '') !== 'DEPARTMENT_HEAD') return null;
    try {
        $s = db()->prepare("SELECT department_id FROM employees WHERE user_id=? AND deleted_at IS NULL LIMIT 1");
        $s->execute([$_SESSION['user']['id']]);
        $dept = $s->fetchColumn();
    } catch (Exception $e) { return []; }
    return $dept ? [$dept] : [];
}
function login_user(string $employeeNumber, string $password): array {
    $employeeNumber = trim($employeeNumber);
    $generic = 'Invalid employee number or password';
    if ($employeeNumber === '' || $password === '') return [false, $generic];
    $stmt = db()->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.employee_number=? AND u.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$employeeNumber]);
    $user = $stmt->fetch();
    if (!$user) {
        // Same generic error — never reveal whether the number exists.
        return [false, $generic];
    }
    // Deactivated accounts are blocked (checked before password so a correct
    // password on a resigned account still cannot proceed).
    if (($user['account_state'] ?? 'ACTIVE') === 'DEACTIVATED') {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $s = db()->prepare("INSERT INTO audit_logs (id,user_id,action,resource,resource_id,details,ip_address,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
            $s->execute([uuid(), $user['id'], 'LOGIN_BLOCKED', 'auth', $user['id'], json_encode(['reason' => 'deactivated']), $ip]);
        } catch (Exception $e) {}
        return [false, 'Account is deactivated. Please contact HR.'];
    }
    // Rate limit: 5 failed attempts lock the account for 15 minutes.
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $mins = (int)ceil((strtotime($user['locked_until']) - time()) / 60);
        // DB and PHP clocks can differ by timezone; lockout is always 15 min.
        $mins = max(1, min(15, $mins));
        return [false, 'Account locked due to too many failed attempts. Try again in ' . $mins . ' minute(s).'];
    }
    if (!password_verify($password, $user['password'])) {
        try {
            $fails = (int)($user['failed_attempts'] ?? 0) + 1;
            if ($fails >= 5) {
                db()->prepare("UPDATE users SET failed_attempts=?, locked_until=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id=?")->execute([$fails, $user['id']]);
            } else {
                db()->prepare("UPDATE users SET failed_attempts=? WHERE id=?")->execute([$fails, $user['id']]);
            }
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $s = db()->prepare("INSERT INTO audit_logs (id,user_id,action,resource,resource_id,details,ip_address,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
            $s->execute([uuid(), $user['id'], 'LOGIN_FAILED', 'auth', $user['id'], json_encode(['attempts' => $fails]), $ip]);
        } catch (Exception $e) {}
        // On the 5th failure tell the user about the lockout, otherwise generic.
        if (((int)($user['failed_attempts'] ?? 0) + 1) >= 5) {
            return [false, 'Account locked due to too many failed attempts. Try again in 15 minutes.'];
        }
        return [false, $generic];
    }
    // Success: reset lockout, record last login, regenerate session id.
    try {
        db()->prepare("UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?")->execute([$user['id']]);
    } catch (Exception $e) {}
    // fetch employee name if exists
    $stmt2 = db()->prepare("SELECT first_name,last_name FROM employees WHERE user_id=? LIMIT 1");
    $stmt2->execute([$user['id']]);
    $emp = $stmt2->fetch();
    $name = $emp ? ($emp['first_name'].' '.$emp['last_name']) : ($user['email'] ?? $user['employee_number']);
    safe_session_regenerate();
    $_SESSION['user'] = [
        'id'=>$user['id'],
        'email'=>$user['email'],
        'employee_number'=>$user['employee_number'],
        'role'=>$user['role_name'],
        'role_id'=>$user['role_id'],
        'name'=>$name,
        'account_state'=>$user['account_state'] ?? 'ACTIVE',
    ];
    return [true, ''];
}

/**
 * Enforce FIRST_LOGIN / PENDING_201 / DEACTIVATED on every authenticated
 * request (proxy equivalent). Only the whitelisted onboarding screens are
 * reachable while in a restricted state.
 */
function enforce_account_state(): void {
    if (!is_logged_in()) return;
    // Refresh state from DB so deactivation takes effect immediately.
    try {
        $s = db()->prepare("SELECT account_state, employee_number FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1");
        $s->execute([$_SESSION['user']['id']]);
        $row = $s->fetch();
        if (!$row) { logout_user(); redirect(base_url('login.php')); }
        $_SESSION['user']['account_state'] = $row['account_state'] ?? 'ACTIVE';
        $_SESSION['user']['employee_number'] = $row['employee_number'] ?? ($_SESSION['user']['employee_number'] ?? null);
    } catch (Exception $e) { return; }
    $state = $_SESSION['user']['account_state'] ?? 'ACTIVE';
    if ($state === 'ACTIVE') return;
    $path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $is = function (string $suffix) use ($path): bool {
        return strpos($path . '/', '/' . $suffix) !== false;
    };
    if ($state === 'DEACTIVATED') {
        logout_user();
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['_login_error'] = 'Account is deactivated. Please contact HR.';
        redirect(base_url('login.php'));
    }
    if ($state === 'FIRST_LOGIN') {
        // Only the change-password screen (and logout / static assets) is reachable.
        if ($is('change-password.php') || $is('logout.php') || $is('assets/')) return;
        redirect(base_url('change-password.php'));
    }
    if ($state === 'PENDING_201') {
        // Only the 201 file form (+ password change + logout) is reachable.
        if ($is('201-file.php') || $is('onboarding/') || $is('change-password.php') || $is('logout.php') || $is('assets/') || $is('documents/')) return;
        redirect(base_url('onboarding/201-file.php'));
    }
}

/** Refresh the session snapshot after an account change. */
function refresh_account_session(): void {
    if (!is_logged_in()) return;
    try {
        $s = db()->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1");
        $s->execute([$_SESSION['user']['id']]);
        $u = $s->fetch();
        if (!$u) return;
        $_SESSION['user']['account_state'] = $u['account_state'] ?? 'ACTIVE';
        $_SESSION['user']['employee_number'] = $u['employee_number'] ?? null;
        $_SESSION['user']['email'] = $u['email'] ?? null;
        $_SESSION['user']['role'] = $u['role_name'] ?? $_SESSION['user']['role'];
    } catch (Exception $e) {}
}

/** Next free Employee Number in YYYY-NNNN format (year = current year). */
function next_employee_number(?string $year = null): string {
    $year = $year ?? date('Y');
    try {
        $s = db()->prepare("SELECT IFNULL(MAX(CAST(SUBSTRING(employee_number, 6) AS UNSIGNED)), 0) AS m FROM users WHERE employee_number LIKE ?");
        $s->execute([$year . '-%']);
        $max = (int)($s->fetch()['m'] ?? 0);
    } catch (Exception $e) { $max = 0; }
    return $year . '-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
}

/** Validate a new password for first-time / change flows. Returns error or ''. */
function validate_new_password(string $new, string $employeeNumber, string $currentHash = ''): string {
    if ($employeeNumber !== '' && hash_equals($employeeNumber, $new)) return 'Password cannot be your employee number.';
    if (strlen($new) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) return 'Password must contain a letter and a number.';
    if ($currentHash !== '' && password_verify($new, $currentHash)) return 'Password cannot be the same as the current password.';
    return '';
}

/** Mandatory 201 document type names (configurable in Documents settings). */
function mandatory_doc_types(): array {
    try {
        return db()->query("SELECT name FROM document_types WHERE mandatory=1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) { return ['Birth Certificate', 'Valid ID', 'NBI Clearance']; }
}

/** All configured document types for dropdowns. */
function all_doc_types(): array {
    try {
        return db()->query("SELECT name, mandatory FROM document_types ORDER BY mandatory DESC, name")->fetchAll() ?: [];
    } catch (Exception $e) { return []; }
}

/**
 * 201 progress for an employee: 4 profile items + each mandatory doc type.
 * Returns ['done'=>int,'total'=>int,'items'=>[['label'=>, 'done'=>bool]],'can_submit'=>bool].
 */
function get_201_progress(string $employeeId): array {
    $pdo = db();
    try {
        $p = $pdo->prepare("SELECT phone,address,date_of_birth,emergency_contact FROM employee_profiles WHERE employee_id=? LIMIT 1");
        $p->execute([$employeeId]); $prof = $p->fetch() ?: [];
        $d = $pdo->prepare("SELECT DISTINCT type FROM documents WHERE employee_id=?");
        $d->execute([$employeeId]);
        $have = array_map('strtolower', array_map('trim', $d->fetchAll(PDO::FETCH_COLUMN) ?: []));
    } catch (Exception $e) { $prof = []; $have = []; }
    $items = [
        ['label' => 'Phone', 'done' => !empty($prof['phone'])],
        ['label' => 'Address', 'done' => !empty($prof['address'])],
        ['label' => 'Date of birth', 'done' => !empty($prof['date_of_birth'])],
        ['label' => 'Emergency contact', 'done' => !empty($prof['emergency_contact'])],
    ];
    foreach (mandatory_doc_types() as $t) {
        $items[] = ['label' => $t, 'done' => in_array(strtolower(trim($t)), $have, true)];
    }
    $done = count(array_filter($items, fn($i) => $i['done']));
    return ['done' => $done, 'total' => count($items), 'items' => $items, 'can_submit' => $done === count($items) && count($items) > 0];
}

/**
 * Day 1 / 3 / 7 in-app reminders for FIRST_LOGIN + PENDING_201 accounts.
 * Called on login and dashboard visits; inserts at most one notification
 * per (user, day-marker). Deadline is reminder-only — never locks.
 */
function maybe_send_onboarding_reminder(string $userId): void {
    try {
        $pdo = db();
        $u = $pdo->prepare("SELECT id, account_state, DATEDIFF(CURDATE(), DATE(created_at)) as days FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1");
        $u->execute([$userId]); $row = $u->fetch();
        if (!$row || !in_array($row['account_state'] ?? '', ['FIRST_LOGIN', 'PENDING_201'], true)) return;
        $days = (int)($row['days'] ?? 0);
        if (!in_array($days, [1, 3, 7], true)) return;
        $marker = 'onboarding_day_' . $days;
        $chk = $pdo->prepare("SELECT id FROM notifications WHERE user_id=? AND type=? LIMIT 1");
        $chk->execute([$userId, $marker]);
        if ($chk->fetch()) return;
        $title = $row['account_state'] === 'FIRST_LOGIN' ? 'Complete your first login' : 'Complete your 201 file';
        $msg = $row['account_state'] === 'FIRST_LOGIN'
            ? "Day $days: please change your default password to unlock your account."
            : "Day $days: your 201 file is still incomplete. Submit it before the 14-day reminder deadline.";
        $pdo->prepare("INSERT INTO notifications (id,user_id,type,title,message) VALUES (?,?,?,?,?)")
            ->execute([uuid(), $userId, $marker, $title, $msg]);
    } catch (Exception $e) {}
}

/** HR/Super Admin widget data: never-logged-in or incomplete-201, with days pending. */
function pending_onboarders(): array {
    try {
        return db()->query(
            "SELECT u.id as user_id, u.employee_number, u.account_state, u.created_at, u.last_login,
                    CONCAT(e.first_name,' ',e.last_name) as name, e.id as employee_id,
                    ep.is_201_submitted, ep.onboarding_deadline,
                    DATEDIFF(CURDATE(), DATE(u.created_at)) as days_pending
             FROM users u LEFT JOIN employees e ON e.user_id=u.id AND e.deleted_at IS NULL
             LEFT JOIN employee_profiles ep ON ep.employee_id=e.id
             WHERE u.deleted_at IS NULL AND u.account_state IN ('FIRST_LOGIN','PENDING_201')
             ORDER BY u.created_at ASC"
        )->fetchAll() ?: [];
    } catch (Exception $e) { return []; }
}

/** Validate an uploaded document. Returns '' when OK, else an error message. */
function validate_upload_file(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return 'File upload failed.';
    $max = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max) return 'File too large (max 5 MB).';
    $allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) return 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.';
    // Double-check images really are images.
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) return 'Invalid image file.';
    }
    return '';
}
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
