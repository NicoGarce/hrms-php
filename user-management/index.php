<?php
// User Management — Super Admin only.
// User functions moved out of Settings > Users (that page now redirects here).
// Settings keeps company settings, role permissions, audit logs, notifications.
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
$pdo = db();
$selfId = $_SESSION['user']['id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    $uid = $_POST['user_id'] ?? '';

    $fetch = function (string $id) use ($pdo) {
        $s = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.deleted_at IS NULL LIMIT 1");
        $s->execute([$id]);
        return $s->fetch();
    };

    try {
        if ($action === 'create') {
            $email = trim($_POST['email'] ?? '');
            $roleId = $_POST['role_id'] ?? '';
            $first = trim($_POST['first_name'] ?? ''); $last = trim($_POST['last_name'] ?? '');
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Valid email is required.');
            if (!$roleId) throw new Exception('Role is required.');
            if (!$first || !$last) throw new Exception('First and last name are required (used for the linked employee record).');
            $chk = $pdo->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$email]);
            if ($chk->fetch()) throw new Exception('Email already exists.');
            $empNo = next_employee_number();
            $c = $pdo->prepare("SELECT id FROM users WHERE employee_number=?"); $c->execute([$empNo]);
            if ($c->fetch()) throw new Exception('Number collision, try again.');
            $newId = uuid(); $eid = uuid();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO users (id,email,employee_number,password,role_id,account_state) VALUES (?,?,?,?,?,'FIRST_LOGIN')")
                    ->execute([$newId, $email, $empNo, password_hash($empNo, PASSWORD_BCRYPT), $roleId]);
                // Every account gets a linked employee record so first-login onboarding can proceed.
                $pdo->prepare("INSERT INTO employees (id,user_id,employee_code,first_name,last_name,hire_date,status) VALUES (?,?,?,?,?,CURDATE(),'PROBATION')")
                    ->execute([$eid, $newId, $empNo, $first, $last]);
                $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,onboarding_deadline) VALUES (?,?,DATE_ADD(CURDATE(), INTERVAL 14 DAY))")->execute([uuid(), $eid]);
                $pdo->commit();
            } catch (Exception $e) { $pdo->rollBack(); throw $e; }
            audit_log('CREATE', 'users', $newId, ['email' => $email, 'employee_number' => $empNo]);
            flash('success', 'Account created: ' . $empNo . ' (FIRST_LOGIN, default password is the employee number).');
        } elseif ($action === 'edit') {
            $target = $fetch($uid);
            if (!$target) throw new Exception('Account not found.');
            $email = trim($_POST['email'] ?? '');
            $roleId = $_POST['role_id'] ?? $target['role_id'];
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Valid email is required.');
            if ($uid === $selfId && $roleId !== $target['role_id']) throw new Exception('You cannot change your own role.');
            $chk = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?"); $chk->execute([$email, $uid]);
            if ($chk->fetch()) throw new Exception('Email already taken.');
            $pdo->prepare("UPDATE users SET email=?, role_id=? WHERE id=?")->execute([$email, $roleId, $uid]);
            audit_log('UPDATE', 'users', $uid, ['email' => $email, 'role_changed' => $roleId !== $target['role_id']]);
            flash('success', 'Account updated.');
        } elseif ($action === 'activate' || $action === 'deactivate') {
            $target = $fetch($uid);
            if (!$target) throw new Exception('Account not found.');
            if ($uid === $selfId) throw new Exception('You cannot deactivate your own account.');
            $newState = $action === 'activate' ? 'ACTIVE' : 'DEACTIVATED';
            $pdo->prepare("UPDATE users SET account_state=?, failed_attempts=0, locked_until=NULL WHERE id=?")->execute([$newState, $uid]);
            audit_log($action === 'activate' ? 'ACTIVATE' : 'DEACTIVATE', 'users', $uid, ['from' => $target['account_state'], 'to' => $newState]);
            flash('success', $action === 'activate' ? 'Account activated.' : 'Account deactivated. Login is blocked.');
        } elseif ($action === 'unlock') {
            $target = $fetch($uid);
            if (!$target) throw new Exception('Account not found.');
            $pdo->prepare("UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=?")->execute([$uid]);
            audit_log('UNLOCK', 'users', $uid, null);
            flash('success', 'Account unlocked.');
        } elseif ($action === 'reset') {
            // Reset login details: password -> employee number, state -> FIRST_LOGIN.
            // Keeps the 201 file and documents untouched.
            $target = $fetch($uid);
            if (!$target) throw new Exception('Account not found.');
            if (empty($target['employee_number'])) throw new Exception('Account has no employee number.');
            $pdo->prepare("UPDATE users SET password=?, account_state='FIRST_LOGIN', failed_attempts=0, locked_until=NULL WHERE id=?")
                ->execute([password_hash($target['employee_number'], PASSWORD_BCRYPT), $uid]);
            audit_log('RESET', 'users', $uid, ['to_state' => 'FIRST_LOGIN']);
            flash('success', 'Login reset: password is now the employee number and the account is back to FIRST_LOGIN. 201 file and documents kept.');
        } elseif ($action === 'reopen') {
            // Optional "Reopen 201 file": keeps documents, clears submission, back to PENDING_201.
            $target = $fetch($uid);
            if (!$target) throw new Exception('Account not found.');
            if ($target['account_state'] === 'DEACTIVATED') throw new Exception('Activate the account first.');
            $e = $pdo->prepare("SELECT id FROM employees WHERE user_id=? AND deleted_at IS NULL LIMIT 1");
            $e->execute([$uid]); $empId = $e->fetchColumn();
            if (!$empId) throw new Exception('No employee record linked.');
            $pdo->prepare("UPDATE employee_profiles SET is_201_submitted=0, submitted_201_at=NULL WHERE employee_id=?")->execute([$empId]);
            $pdo->prepare("UPDATE users SET account_state='PENDING_201' WHERE id=?")->execute([$uid]);
            audit_log('REOPEN', '201_file', $empId, ['by' => $selfId]);
            flash('success', '201 file reopened (documents kept). Account is PENDING_201.');
        }
    } catch (Exception $e) {
        flash('error', $e->getMessage());
    }
    redirect(base_url('user-management/'));
}

// Filters: employee number, name, role, account state, last login.
$q = trim($_GET['q'] ?? '');
$roleF = trim($_GET['role'] ?? '');
$stateF = trim($_GET['state'] ?? '');
$loginF = trim($_GET['login'] ?? '');
$sql = "SELECT u.*, r.name as role_name, CONCAT(e.first_name,' ',e.last_name) as emp_name FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN employees e ON e.user_id=u.id AND e.deleted_at IS NULL WHERE u.deleted_at IS NULL";
$params = [];
if ($q !== '') { $sql .= " AND (u.employee_number LIKE ? OR u.email LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)"; $like = "%$q%"; $params = [$like, $like, $like, $like]; }
if ($roleF !== '') { $sql .= " AND r.name=?"; $params[] = $roleF; }
if ($stateF !== '') { $sql .= " AND u.account_state=?"; $params[] = $stateF; }
if ($loginF === 'NEVER') $sql .= " AND u.last_login IS NULL";
elseif ($loginF === 'INACTIVE_30') $sql .= " AND (u.last_login IS NULL OR u.last_login < DATE_SUB(NOW(), INTERVAL 30 DAY))";
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $users = $stmt->fetchAll();
$roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();

$qs = array_filter(['q' => $q, 'role' => $roleF, 'state' => $stateF, 'login' => $loginF]);
layout_header('User Management', 'Accounts, roles, lockouts and resets — Super Admin only', 'teal');
?>
<div style="display:flex;gap:0.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;justify-content:space-between;">
  <form method="GET" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
    <input name="q" value="<?= e($q) ?>" placeholder="Number, name, email..." class="input" style="width:14rem;">
    <select name="role" class="input" style="width:11rem;"><option value="">All Roles</option><?php foreach ($roles as $r): ?><option value="<?= e($r['name']) ?>" <?= $roleF == $r['name'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select>
    <select name="state" class="input" style="width:10rem;"><option value="">All States</option><?php foreach (['FIRST_LOGIN','PENDING_201','ACTIVE','DEACTIVATED'] as $s): ?><option <?= $stateF == $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <select name="login" class="input" style="width:11rem;"><option value="">Any Login</option><option value="NEVER" <?= $loginF=='NEVER'?'selected':'' ?>>Never logged in</option><option value="INACTIVE_30" <?= $loginF=='INACTIVE_30'?'selected':'' ?>>Inactive 30+ days</option></select>
    <button class="btn btn-outline btn-sm">Filter</button>
    <?php if ($qs): ?><a href="<?= base_url('user-management/') ?>" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
  </form>
  <button onclick="document.getElementById('dlg-add').showModal()" class="btn btn-primary btn-sm">Add Account</button>
</div>

<div class="card tab-notch tab-teal"><div class="card-content" style="padding:0;"><div class="table-wrap"><table>
<thead><tr><th>Employee No.</th><th>Name / Email</th><th>Role</th><th>State</th><th>Last Login</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($users as $u): $isSelf = $u['id'] === $selfId; $locked = !empty($u['locked_until']) && strtotime($u['locked_until']) > time(); ?>
<tr>
  <td style="font-family:var(--font-mono);font-size:0.8125rem;"><?= e($u['employee_number'] ?? '—') ?></td>
  <td><div style="font-weight:500;"><?= e(trim((string)($u['emp_name'] ?? '')) !== '' ? $u['emp_name'] : '(no employee)') ?></div><div style="font-size:0.75rem;color:var(--muted-foreground);"><?= e($u['email']) ?></div></td>
  <td><span class="badge badge-slate"><?= e($u['role_name']) ?></span></td>
  <td><span class="badge <?= $u['account_state']=='ACTIVE'?'badge-green':($u['account_state']=='DEACTIVATED'?'badge-brick':'badge-brass') ?>"><?= e($u['account_state']) ?></span><?= $locked ? ' <span class="badge badge-brick">LOCKED</span>' : '' ?></td>
  <td style="font-size:0.8125rem;"><?= $u['last_login'] ? e(date('M d, Y H:i', strtotime($u['last_login']))) : '<span style="color:var(--muted-foreground);">Never</span>' ?></td>
  <td><div style="display:flex;gap:0.25rem;flex-wrap:wrap;">
    <button onclick='openEdit(<?= json_encode($u['id']) ?>,<?= json_encode($u['email']) ?>,<?= json_encode($u['role_id']) ?>)' class="btn btn-outline btn-sm" style="height:1.5rem;">Edit</button>
    <?php if (!$isSelf): ?>
      <?php if ($u['account_state'] === 'DEACTIVATED'): ?>
      <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><input type="hidden" name="action" value="activate"><button class="btn btn-primary btn-sm" style="height:1.5rem;" onclick="return confirm('Activate this account?')">Activate</button></form>
      <?php else: ?>
      <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><input type="hidden" name="action" value="deactivate"><button class="btn btn-ghost btn-sm" style="height:1.5rem;color:var(--destructive);" onclick="return confirm('Deactivate this account? Login will be blocked.')">Deactivate</button></form>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($locked): ?>
    <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><input type="hidden" name="action" value="unlock"><button class="btn btn-outline btn-sm" style="height:1.5rem;">Unlock</button></form>
    <?php endif; ?>
    <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><input type="hidden" name="action" value="reset"><button class="btn btn-outline btn-sm" style="height:1.5rem;" onclick="return confirm('Reset login? Password returns to the Employee Number and state returns to FIRST_LOGIN. 201 file and documents are kept.')">Reset login</button></form>
    <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><input type="hidden" name="action" value="reopen"><button class="btn btn-ghost btn-sm" style="height:1.5rem;" onclick="return confirm('Reopen the 201 file? Documents are kept; the account goes back to PENDING_201.')">Reopen 201</button></form>
  </div></td>
</tr>
<?php endforeach; ?>
<?php if (empty($users)): ?><tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--muted-foreground);">No accounts found</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>
<p style="font-size:0.75rem;color:var(--muted-foreground);margin-top:0.5rem;">You cannot deactivate or demote your own account. Every action here is audit-logged, including failed logins and password changes (see Settings → Audit Logs).</p>

<dialog id="dlg-add" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:26rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Add Account</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
<div><label style="font-size:0.8125rem;">Email (contact only) *</label><input name="email" type="email" required class="input"></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
<div><label style="font-size:0.8125rem;">First name *</label><input name="first_name" required class="input"></div>
<div><label style="font-size:0.8125rem;">Last name *</label><input name="last_name" required class="input"></div>
</div>
<div><label style="font-size:0.8125rem;">Role *</label><select name="role_id" required class="input"><option value="">Select</option><?php foreach ($roles as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
<p style="font-size:0.75rem;color:var(--muted-foreground);">Employee Number is auto-generated; the default password is that number and the account starts in FIRST_LOGIN. For regular staff, prefer Employees → Add Employee instead.</p>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Create</button></div>
</form>
</dialog>

<dialog id="dlg-edit" style="border:1px solid var(--border);border-radius:0.75rem;padding:0;max-width:26rem;width:calc(100% - 2rem);">
<form method="POST" style="padding:1.25rem;display:grid;gap:0.75rem;">
<h3 style="font-weight:600;">Edit Account</h3>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="user_id" id="edit-id">
<div><label style="font-size:0.8125rem;">Email (contact only) *</label><input name="email" id="edit-email" type="email" required class="input"></div>
<div><label style="font-size:0.8125rem;">Role (Super Admin only) *</label><select name="role_id" id="edit-role" required class="input"><?php foreach ($roles as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
<div style="display:flex;gap:0.5rem;justify-content:flex-end;"><button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost">Cancel</button><button class="btn btn-primary">Save</button></div>
</form>
</dialog>
<script>
function openEdit(id,email,roleId){ document.getElementById('edit-id').value=id; document.getElementById('edit-email').value=email; document.getElementById('edit-role').value=roleId; document.getElementById('dlg-edit').showModal(); }
</script>
<?php layout_footer(); ?>
