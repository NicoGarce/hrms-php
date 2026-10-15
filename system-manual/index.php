<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
layout_header('System Manual','HRMS PHP — User guide','teal');
?>
<div style="display:grid;gap:1rem;max-width:50rem;">
<div class="card tab-notch tab-teal"><div class="card-header"><div class="card-title">Overview</div></div><div class="card-content" style="font-size:0.875rem;line-height:1.6;color:var(--muted-foreground);">
<p>This HRMS PHP system is a faithful port of the Next.js HRMS. It keeps the same styling (cool paper background, ledger cover sidebar, tab-notch cards) and the same flow (dashboard → modules).</p>
<ul style="margin-top:0.5rem;padding-left:1.25rem;display:grid;gap:0.25rem;">
<li><strong style="color:var(--foreground);">Stack:</strong> PHP 8 + MySQL (PDO), vanilla JS, Lucide icons, CSS variables (OKLCH)</li>
<li><strong style="color:var(--foreground);">Auth:</strong> Session + bcrypt, role-based navigation (SUPER_ADMINISTRATOR, HR_ADMINISTRATOR, DEPARTMENT_HEAD, EMPLOYEE)</li>
<li><strong style="color:var(--foreground);">Database:</strong> Import <code>database.sql</code> via phpMyAdmin. DB name <code>hrms_php</code></li>
<li><strong style="color:var(--foreground);">Credentials:</strong> See LOGIN_CREDENTIALS — admin@company.com / AdminPass123!</li>
</ul>
</div></div>

<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Modules</div></div><div class="card-content" style="font-size:0.875rem;line-height:1.6;">
<div style="display:grid;gap:0.75rem;">
<div><strong>Dashboard</strong> — Greeting, quick actions, stats (headcount, present, pending leaves, open jobs), attendance trend, department distribution, announcements.</div>
<div><strong>Employees</strong> — List/search, add, view, edit, soft-delete. Departments/positions linked.</div>
<div><strong>Departments & Positions</strong> — CRUD with counts, linked to employees.</div>
<div><strong>Recruitment</strong> — Jobs (DRAFT/OPEN/CLOSED) + Applicants (APPLIED→HIRED) with status updates & convert to employee.</div>
<div><strong>Attendance</strong> — Daily grid by date, holiday/leave overrides, check-in/out, status PRESENT/ABSENT/LATE/HALF_DAY. Holidays manager.</div>
<div><strong>Leave</strong> — Requests with type/dates, approval flow PENDING→DEPARTMENT_APPROVED→APPROVED, leave types, calendar.</div>
<div><strong>Payroll & Payslips</strong> — Payroll by month/year with base/gross/net, items (allowance/deduction/bonus), personal payslip view.</div>
<div><strong>Performance</strong> — Reviews per period with rating, goals, feedback, draft/submit & acknowledge.</div>
<div><strong>Documents</strong> — Upload/list per employee, type filter, file uploads to assets/uploads.</div>
<div><strong>Reports</strong> — Employees, attendance, leave, payroll, analytics with CSV export.</div>
<div><strong>Settings</strong> — Company info, theme picker (teal/warm/slate/forest/dusk), users/roles/audit logs/notifications.</div>
</div>
</div></div>

<div class="card tab-notch tab-brass"><div class="card-header"><div class="card-title">Setup</div></div><div class="card-content" style="font-size:0.875rem;line-height:1.6;">
<ol style="padding-left:1.25rem;display:grid;gap:0.25rem;">
<li>Create database <code>hrms_php</code> and import <code>database.sql</code> (phpMyAdmin → Import).</li>
<li>Ensure <code>config/config.php</code> has correct DB credentials (default root / no password for XAMPP).</li>
<li>Visit <code>/hrms-php/login.php</code> and sign in.</li>
<li>Theme is stored in <code>company_settings.theme</code> — change via Settings.</li>
</ol>
</div></div>
</div>
<?php layout_footer(); ?>
