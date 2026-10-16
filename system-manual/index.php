<?php
require_once __DIR__.'/../includes/auth.php';
require_role(['SUPER_ADMINISTRATOR']);
require_once __DIR__.'/../includes/layout.php';
layout_header('System Manual','HRMS PHP — User guide','teal');
?>
<div style="display:grid;gap:1rem;max-width:52rem;">
<div class="card tab-notch tab-teal"><div class="card-header"><div class="card-title">Overview</div></div><div class="card-content" style="font-size:0.875rem;line-height:1.6;color:var(--muted-foreground);">
<p>PHP + MySQL HRMS (PDO, vanilla JS, Lucide icons, OKLCH themes).</p>
<ul style="margin-top:0.5rem;padding-left:1.25rem;display:grid;gap:0.25rem;">
<li><strong style="color:var(--foreground);">Sign-in:</strong> Employee Number + password (session-based, bcrypt). Email is contact-only.</li>
<li><strong style="color:var(--foreground);">Roles:</strong> SUPER_ADMINISTRATOR, HR_ADMINISTRATOR, DEPARTMENT_HEAD, EMPLOYEE.</li>
<li><strong style="color:var(--foreground);">Account states:</strong> FIRST_LOGIN (change password only) → PENDING_201 (201 file only) → ACTIVE (full access). DEACTIVATED cannot log in. 5 failed attempts lock the account for 15 minutes.</li>
<li><strong style="color:var(--foreground);">First-time flow:</strong> log in with the Employee Number as both username and password → set a personal password (8+ chars, letter + number, not the number) → complete and submit the 201 file → ACTIVE.</li>
<li><strong style="color:var(--foreground);">Database:</strong> <code>database.sql</code> for fresh installs; existing installs run <code>php database/migrations/migrate.php</code> (preserves data).</li>
</ul>
</div></div>

<div class="card tab-notch tab-slate"><div class="card-header"><div class="card-title">Modules</div></div><div class="card-content" style="font-size:0.875rem;line-height:1.6;">
<div style="display:grid;gap:0.75rem;">
<div><strong>Dashboard</strong> — Date line plus the Activity &amp; Announcements hero (announcements support memo attachments). Employees see the hero only; managers also see stats, attendance trend, department distribution and the onboarding watchlist.</div>
<div><strong>Employees</strong> — Search/filter by number, name, department and status. Employee Numbers (YYYY-NNNN) are auto-generated and immutable. Creating an employee provisions a FIRST_LOGIN account (default password = the number); HR never handles credentials. TERMINATED/RESIGNED auto-deactivates the login. Export CSV (Super/HR) exports the filtered list and is audit-logged.</div>
<div><strong>201 File</strong> — Part of the personal profile with draft saving and an "N of M required items" banner. Submission requires all profile items plus every mandatory document type. Educational attainment and career experience are optional entries shown on the profile and employee view. Deadline (14 days) is a reminder only. Day 1/3/7 in-app reminders; HR can remind or extend from the dashboard watchlist.</div>
<div><strong>User Management (Super Admin)</strong> — Search/filter accounts; add, edit, activate/deactivate, unlock lockouts, reset login (password back to the Employee Number, state back to FIRST_LOGIN, 201/docs kept), reopen 201 files. Self-deactivation/demotion is blocked. All actions audit-logged.</div>
<div><strong>Departments &amp; Positions</strong> — CRUD with filled-vs-open headcount (openings per position).</div>
<div><strong>Recruitment</strong> — Jobs with multiple openings (DRAFT/OPEN/CLOSED) and filled-vs-open tracking; applicants pipeline with an ONBOARDING tag — converting a HIRED applicant generates the Employee Number and a FIRST_LOGIN account.</div>
<div><strong>Attendance</strong> — Daily grid, holiday/leave overrides, Excel→CSV import (template provided), tardiness warnings (3+ lates/30 days) with notices that feed performance ratings.</div>
<div><strong>Leave</strong> — Requests with type/dates, approval flow PENDING→DEPARTMENT_APPROVED→APPROVED, leave types, calendar.</div>
<div><strong>Performance</strong> — Super/HR Admins schedule review dates; Department Heads conduct reviews (ratings + feedback) with 90-day tardiness shown and factored in; employees acknowledge.</div>
<div><strong>Documents</strong> — Uploads validated by type/size with randomized names, served only to the owner or HR/Super Admins. The document-type dropdown is configurable (mandatory types gate 201 submission).</div>
<div><strong>Settings</strong> — Company info, theme, role permissions, audit logs, notification preferences. (User accounts moved to User Management.)</div>
<div><strong>Removed:</strong> Payroll, Payslips and Reports were retired from the UI. Their tables remain in the database (archived, untouched) but have no pages, nav entries, guards, search entries or manual coverage. Row-level employee export now lives under Employees → Export CSV.</div>
</div>
</div></div>

<div class="card tab-notch tab-brass"><div class="card-header"><div class="card-title">Setup</div></div><div class="card-content" style="font-size:0.875rem;line-height:1.6;">
<ol style="padding-left:1.25rem;display:grid;gap:0.25rem;">
<li>Fresh install: create database <code>hrms_php</code> and import <code>database.sql</code>.</li>
<li>Existing install: run <code>php database/migrations/migrate.php</code> (adds Employee Numbers, states, 201 fields, document types, openings, review dates, memo column — passwords preserved, existing accounts ACTIVE).</li>
<li>Ensure <code>config/config.php</code> has correct DB credentials.</li>
<li>Sign in at <code>/hrms-php/login.php</code> with your Employee Number (seeded Super Admin: <code>2026-0001</code>).</li>
</ol>
</div></div>
</div>
<?php layout_footer(); ?>
