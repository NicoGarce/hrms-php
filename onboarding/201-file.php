<?php
// 201 file completion: draft saving across sessions, mandatory-doc gating,
// progress banner, 14-day reminder-only deadline.
require_once __DIR__.'/../includes/auth.php';
require_login();
$state = $_SESSION['user']['account_state'] ?? 'ACTIVE';
if ($state === 'FIRST_LOGIN') redirect(base_url('change-password.php'));
if (!in_array($state, ['PENDING_201', 'ACTIVE'], true)) redirect(base_url('dashboard/'));
require_once __DIR__.'/../includes/layout.php';
$pdo = db();

$empStmt = $pdo->prepare("SELECT e.* FROM employees e WHERE e.user_id=? AND e.deleted_at IS NULL LIMIT 1");
$empStmt->execute([$_SESSION['user']['id']]);
$emp = $empStmt->fetch();
if (!$emp) {
    // Account created without an employee record (e.g. via User Management):
    // provision a skeleton so onboarding can proceed instead of looping logout.
    // Names default from the email prefix and are editable in Employees.
    $u = $pdo->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
    $u->execute([$_SESSION['user']['id']]); $urow = $u->fetch();
    $local = explode('@', (string)($urow['email'] ?? 'new employee'))[0];
    $parts = preg_split('/[._\-]+/', $local);
    $first = ucfirst($parts[0] ?? 'New'); $last = ucfirst($parts[1] ?? 'Employee');
    $newEid = uuid();
    $pdo->prepare("INSERT INTO employees (id,user_id,employee_code,first_name,last_name,hire_date,status) VALUES (?,?,?,?,?,CURDATE(),'PROBATION')")
        ->execute([$newEid, $_SESSION['user']['id'], $_SESSION['user']['employee_number'] ?? ('TBD-' . substr($newEid, 0, 8)), $first, $last]);
    $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,onboarding_deadline) VALUES (?,?,DATE_ADD(CURDATE(), INTERVAL 14 DAY))")->execute([uuid(), $newEid]);
    audit_log('CREATE', 'employees', $newEid, ['skeleton' => true, 'for_user' => $_SESSION['user']['id']]);
    $empStmt->execute([$_SESSION['user']['id']]); $emp = $empStmt->fetch();
    flash('success', 'An employee record was created for your account. Please verify your name below.');
    redirect(base_url('onboarding/201-file.php'));
}

$profStmt = $pdo->prepare("SELECT * FROM employee_profiles WHERE employee_id=? LIMIT 1");
$profStmt->execute([$emp['id']]);
$prof = $profStmt->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $phone = trim($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '') ?: null;
    $emergency = trim($_POST['emergency_contact'] ?? '');
    $civil = trim($_POST['civil_status'] ?? '') ?: null;
    $nationality = trim($_POST['nationality'] ?? '') ?: null;
    $sss = trim($_POST['sss_no'] ?? '') ?: null; $tin = trim($_POST['tin_no'] ?? '') ?: null;
    $phil = trim($_POST['philhealth_no'] ?? '') ?: null; $pag = trim($_POST['pagibig_no'] ?? '') ?: null;
    $draft = json_encode(['saved_at' => date('c')]);

    $save = function () use ($pdo, $emp, $phone, $address, $emergency, $dob, $civil, $nationality, $sss, $tin, $phil, $pag, $draft) {
        $pdo->prepare("INSERT INTO employee_profiles (id,employee_id,phone,address,emergency_contact,date_of_birth,civil_status,nationality,sss_no,tin_no,philhealth_no,pagibig_no,draft_201,onboarding_deadline) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,COALESCE((SELECT onboarding_deadline FROM (SELECT onboarding_deadline FROM employee_profiles WHERE employee_id=?) d), DATE_ADD(CURDATE(), INTERVAL 14 DAY))) ON DUPLICATE KEY UPDATE phone=VALUES(phone),address=VALUES(address),emergency_contact=VALUES(emergency_contact),date_of_birth=VALUES(date_of_birth),civil_status=VALUES(civil_status),nationality=VALUES(nationality),sss_no=VALUES(sss_no),tin_no=VALUES(tin_no),philhealth_no=VALUES(philhealth_no),pagibig_no=VALUES(pagibig_no),draft_201=VALUES(draft_201)")
            ->execute([uuid(), $emp['id'], $phone ?: null, $address ?: null, $emergency ?: null, $dob, $civil, $nationality, $sss, $tin, $phil, $pag, $draft, $emp['id']]);
    };

    // Educational attainment entries (optional — never blocks submission).
    if (isset($_POST['add_education'])) {
        $level = trim($_POST['edu_level'] ?? 'College'); $school = trim($_POST['edu_school'] ?? '');
        $course = trim($_POST['edu_course'] ?? '') ?: null; $yr = trim($_POST['edu_year'] ?? '') ?: null;
        if ($school === '') { flash('error', 'School name is required.'); redirect(base_url('onboarding/201-file.php')); }
        $pdo->prepare("INSERT INTO employee_education (id,employee_id,level,school,course,year_graduated) VALUES (?,?,?,?,?,?)")
            ->execute([uuid(), $emp['id'], $level, $school, $course, $yr]);
        audit_log('CREATE', 'employee_education', $emp['id'], ['school' => $school]);
        flash('success', 'Education added.');
        redirect(base_url('onboarding/201-file.php'));
    }
    if (isset($_POST['del_education'])) {
        $pdo->prepare("DELETE FROM employee_education WHERE id=? AND employee_id=?")->execute([$_POST['edu_id'] ?? '', $emp['id']]);
        flash('success', 'Education entry removed.');
        redirect(base_url('onboarding/201-file.php'));
    }
    // Career experience entries (optional — never blocks submission).
    if (isset($_POST['add_experience'])) {
        $company = trim($_POST['exp_company'] ?? ''); $position = trim($_POST['exp_position'] ?? '');
        $start = trim($_POST['exp_start'] ?? '') ?: null; $end = trim($_POST['exp_end'] ?? '') ?: null;
        $desc = trim($_POST['exp_desc'] ?? '') ?: null;
        if ($company === '' || $position === '') { flash('error', 'Company and position are required.'); redirect(base_url('onboarding/201-file.php')); }
        $pdo->prepare("INSERT INTO employee_experience (id,employee_id,company,position,start_date,end_date,description) VALUES (?,?,?,?,?,?,?)")
            ->execute([uuid(), $emp['id'], $company, $position, $start, $end, $desc]);
        audit_log('CREATE', 'employee_experience', $emp['id'], ['company' => $company]);
        flash('success', 'Experience added.');
        redirect(base_url('onboarding/201-file.php'));
    }
    if (isset($_POST['del_experience'])) {
        $pdo->prepare("DELETE FROM employee_experience WHERE id=? AND employee_id=?")->execute([$_POST['exp_id'] ?? '', $emp['id']]);
        flash('success', 'Experience entry removed.');
        redirect(base_url('onboarding/201-file.php'));
    }
    // Inline document upload with type + size validation and randomized name.
    if (isset($_POST['upload_doc'])) {        $type = trim($_POST['doc_type'] ?? '');
        $err = '';
        if ($type === '') $err = 'Choose a document type.';
        elseif (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] === UPLOAD_ERR_NO_FILE) $err = 'Choose a file to upload.';
        else {
            $err = validate_upload_file($_FILES['doc_file']);
            // Type must come from the configured catalogue (prevents free-text bypass).
            if ($err === '') {
                $chk = $pdo->prepare("SELECT name FROM document_types WHERE name=? LIMIT 1");
                $chk->execute([$type]);
                if (!$chk->fetch()) $err = 'Unknown document type.';
            }
        }
        if ($err !== '') { flash('error', $err); redirect(base_url('onboarding/201-file.php')); }
        $ext = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
        $fname = uuid() . '.' . $ext;
        $dest = __DIR__ . '/../assets/uploads/' . $fname;
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0777, true);
        move_uploaded_file($_FILES['doc_file']['tmp_name'], $dest);
        $pdo->prepare("INSERT INTO documents (id,employee_id,type,name,file_url) VALUES (?,?,?,?,?)")
            ->execute([uuid(), $emp['id'], $type, $_FILES['doc_file']['name'], base_url('assets/uploads/' . $fname)]);
        $save();
        audit_log('UPLOAD', 'documents', $emp['id'], ['type' => $type]);
        flash('success', $type . ' uploaded.');
        redirect(base_url('onboarding/201-file.php'));
    }

    if (isset($_POST['save_draft'])) {
        $save();
        audit_log('UPDATE', '201_file', $emp['id'], ['draft' => true]);
        flash('success', 'Draft saved. You can continue later.');
        redirect(base_url('onboarding/201-file.php'));
    }
    if (isset($_POST['submit_201'])) {
        $save();
        $progress = get_201_progress($emp['id']);
        if (!$progress['can_submit']) {
            $missing = array_map(fn($i) => $i['label'], array_filter($progress['items'], fn($i) => !$i['done']));
            flash('error', 'Submission blocked — still missing: ' . implode(', ', $missing));
            redirect(base_url('onboarding/201-file.php'));
        }
        $pdo->prepare("UPDATE employee_profiles SET is_201_submitted=1, submitted_201_at=NOW() WHERE employee_id=?")->execute([$emp['id']]);
        if ($state === 'PENDING_201') {
            $pdo->prepare("UPDATE users SET account_state='ACTIVE' WHERE id=?")->execute([$_SESSION['user']['id']]);
        }
        refresh_account_session();
        audit_log('SUBMIT', '201_file', $emp['id'], ['items' => $progress['total']]);
        flash('success', '201 file submitted. Your account is now active.');
        redirect(base_url('dashboard/'));
    }
}

$progress = get_201_progress($emp['id']);
$profStmt->execute([$emp['id']]); $prof = $profStmt->fetch() ?: [];
$docsStmt = $pdo->prepare("SELECT * FROM documents WHERE employee_id=? ORDER BY created_at DESC");
$docsStmt->execute([$emp['id']]); $docs = $docsStmt->fetchAll();
$eduStmt = $pdo->prepare("SELECT * FROM employee_education WHERE employee_id=? ORDER BY created_at DESC");
$eduStmt->execute([$emp['id']]); $eduList = $eduStmt->fetchAll();
$expStmt = $pdo->prepare("SELECT * FROM employee_experience WHERE employee_id=? ORDER BY start_date DESC");
$expStmt->execute([$emp['id']]); $expList = $expStmt->fetchAll();
$types = all_doc_types();
$deadline = $prof['onboarding_deadline'] ?? null;

layout_header('Complete your 201 file', 'Draft saving across sessions — submit when every required item is done', 'teal');
?>
<div style="border:1px solid var(--border);background:var(--card);border-radius:0.75rem;padding:0.9rem 1rem;margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
    <strong><?= (int)$progress['done'] ?> of <?= (int)$progress['total'] ?> required items</strong>
    <?php if ($deadline): ?><span style="font-size:0.8125rem;color:var(--muted-foreground);">Reminder deadline: <?= e(date('M d, Y', strtotime($deadline))) ?> (reminder only — no lockout)</span><?php endif; ?>
  </div>
  <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.6rem;">
    <?php foreach ($progress['items'] as $it): ?>
      <span class="badge <?= $it['done'] ? 'badge-green' : 'badge-brass' ?>"><?= $it['done'] ? '✓ ' : '○ ' ?><?= e($it['label']) ?></span>
    <?php endforeach; ?>
  </div>
</div>
<div class="card tab-notch tab-teal"><div class="card-content">
<form method="POST" style="display:grid;gap:0.75rem;">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
    <div><label style="font-size:0.8125rem;font-weight:500;">Phone *</label><input name="phone" class="input" value="<?= e($_POST['phone'] ?? $prof['phone'] ?? '') ?>"></div>
    <div><label style="font-size:0.8125rem;font-weight:500;">Date of Birth *</label><input name="date_of_birth" type="date" class="input" value="<?= e($_POST['date_of_birth'] ?? (isset($prof['date_of_birth']) && $prof['date_of_birth'] ? substr($prof['date_of_birth'], 0, 10) : '')) ?>"></div>
  </div>
  <div><label style="font-size:0.8125rem;font-weight:500;">Address *</label><input name="address" class="input" value="<?= e($_POST['address'] ?? $prof['address'] ?? '') ?>"></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
    <div><label style="font-size:0.8125rem;font-weight:500;">Emergency Contact *</label><input name="emergency_contact" class="input" value="<?= e($_POST['emergency_contact'] ?? $prof['emergency_contact'] ?? '') ?>"></div>
    <div><label style="font-size:0.8125rem;">Civil Status</label><input name="civil_status" class="input" value="<?= e($prof['civil_status'] ?? '') ?>"></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
    <div><label style="font-size:0.8125rem;">Nationality</label><input name="nationality" class="input" value="<?= e($prof['nationality'] ?? '') ?>"></div>
    <div><label style="font-size:0.8125rem;">SSS No.</label><input name="sss_no" class="input" value="<?= e($prof['sss_no'] ?? '') ?>"></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
    <div><label style="font-size:0.8125rem;">TIN No.</label><input name="tin_no" class="input" value="<?= e($prof['tin_no'] ?? '') ?>"></div>
    <div><label style="font-size:0.8125rem;">PhilHealth No.</label><input name="philhealth_no" class="input" value="<?= e($prof['philhealth_no'] ?? '') ?>"></div>
  </div>
  <div><label style="font-size:0.8125rem;">Pag-IBIG No.</label><input name="pagibig_no" class="input" value="<?= e($prof['pagibig_no'] ?? '') ?>"></div>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <button name="save_draft" class="btn btn-outline">Save draft</button>
    <button name="submit_201" class="btn btn-primary" <?= $progress['can_submit'] ? '' : 'disabled title="Complete all required items first"' ?>>Submit 201 file</button>
  </div>
</form>
</div></div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Mandatory documents (<?= count(array_filter($docs, fn($d) => true)) ?> uploaded)</div><div class="card-desc">Upload each required type below — PDF, JPG, PNG, DOC up to 5 MB</div></div>
<div class="card-content" style="display:grid;gap:0.75rem;">
  <form method="POST" enctype="multipart/form-data" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div><label style="font-size:0.8125rem;">Type *</label>
      <select name="doc_type" class="input" style="min-width:12rem;" required><option value="">Select type</option>
      <?php foreach ($types as $t): ?><option value="<?= e($t['name']) ?>"><?= e($t['name']) ?><?= $t['mandatory'] ? ' (required)' : '' ?></option><?php endforeach; ?>
      </select></div>
    <div><label style="font-size:0.8125rem;">File *</label><input type="file" name="doc_file" class="input" required></div>
    <button name="upload_doc" class="btn btn-primary">Upload</button>
  </form>
  <?php if (!$docs): ?><p style="color:var(--muted-foreground);font-size:0.875rem;">No documents yet.</p><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Name</th><th>Type</th><th>Uploaded</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($docs as $d): ?><tr><td><?= e($d['name']) ?></td><td><span class="badge badge-slate"><?= e($d['type']) ?></span></td><td style="font-size:0.8125rem;"><?= e(date('M d, Y', strtotime($d['uploaded_at']))) ?></td><td><a href="<?= base_url('documents/download.php?id=' . e($d['id'])) ?>" class="btn btn-outline btn-sm">Download</a></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div></div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Educational attainment (<?= count($eduList) ?>)</div><div class="card-desc">Optional — does not block submission</div></div>
<div class="card-content" style="display:grid;gap:0.75rem;">
  <form method="POST" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div><label style="font-size:0.8125rem;">Level</label><select name="edu_level" class="input"><option>Elementary</option><option>High School</option><option>College</option><option>Post Graduate</option><option>Vocational</option></select></div>
    <div><label style="font-size:0.8125rem;">School *</label><input name="edu_school" class="input" required></div>
    <div><label style="font-size:0.8125rem;">Course</label><input name="edu_course" class="input" placeholder="e.g. BS Computer Science"></div>
    <div><label style="font-size:0.8125rem;">Year graduated</label><input name="edu_year" class="input" style="width:7rem;" placeholder="2020"></div>
    <button name="add_education" class="btn btn-outline">Add</button>
  </form>
  <?php if ($eduList): ?><div class="table-wrap"><table><thead><tr><th>Level</th><th>School</th><th>Course</th><th>Year</th><th></th></tr></thead><tbody>
  <?php foreach ($eduList as $ed): ?><tr><td><?= e($ed['level']) ?></td><td style="font-weight:500;"><?= e($ed['school']) ?></td><td><?= e($ed['course'] ?? '-') ?></td><td><?= e($ed['year_graduated'] ?? '-') ?></td>
  <td><form method="POST"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="edu_id" value="<?= e($ed['id']) ?>"><button name="del_education" class="btn btn-ghost btn-sm" style="color:var(--destructive);" onclick="return confirmDelete()">Remove</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div></div>
<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Career experience (<?= count($expList) ?>)</div><div class="card-desc">Optional — does not block submission</div></div>
<div class="card-content" style="display:grid;gap:0.75rem;">
  <form method="POST" style="display:grid;gap:0.5rem;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <div><label style="font-size:0.8125rem;">Company *</label><input name="exp_company" class="input" required></div>
      <div><label style="font-size:0.8125rem;">Position *</label><input name="exp_position" class="input" required></div>
      <div><label style="font-size:0.8125rem;">Start</label><input name="exp_start" type="date" class="input"></div>
      <div><label style="font-size:0.8125rem;">End (blank = present)</label><input name="exp_end" type="date" class="input"></div>
    </div>
    <div><label style="font-size:0.8125rem;">Description</label><textarea name="exp_desc" class="input" rows="2"></textarea></div>
    <div><button name="add_experience" class="btn btn-outline">Add experience</button></div>
  </form>
  <?php if ($expList): ?><div class="table-wrap"><table><thead><tr><th>Company</th><th>Position</th><th>Period</th><th></th></tr></thead><tbody>
  <?php foreach ($expList as $ex): ?><tr><td style="font-weight:500;"><?= e($ex['company']) ?></td><td><?= e($ex['position']) ?></td>
  <td style="font-size:0.8125rem;"><?= $ex['start_date'] ? e(date('M Y', strtotime($ex['start_date']))) : '?' ?> – <?= $ex['end_date'] ? e(date('M Y', strtotime($ex['end_date']))) : 'Present' ?></td>
  <td><form method="POST"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="exp_id" value="<?= e($ex['id']) ?>"><button name="del_experience" class="btn btn-ghost btn-sm" style="color:var(--destructive);" onclick="return confirmDelete()">Remove</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div></div>
<?php layout_footer(); ?>
