# Handoff — HRMS-PHP (read this first next session)

## Project
PHP + MySQL HRMS at `C:\xampp\htdocs\hrms-php`. No framework. Auth/nav/guards live in
`includes/auth.php` (this repo's equivalent of `navigation.ts` + `proxy.ts`);
shell in `includes/layout.php`. GitHub: `NicoGarce/hrms-php`, branch `master`.

## What was built (all live via FTP, verified)
- **A. Employee-Number login**: `users.employee_number` (YYYY-NNNN), states
  FIRST_LOGIN → PENDING_201 → ACTIVE / DEACTIVATED enforced per request,
  5-fails/15-min lockout, forced password change (`change-password.php`),
  session regeneration. Seeded Super Admin = `2026-0001`.
- **B. Employees**: number auto-generated + immutable, in search/table; create
  auto-provisions FIRST_LOGIN account (password = number); TERMINATED/RESIGNED
  (and soft-delete) deactivates login; HIRED-applicant conversion creates
  account; Export CSV (Super/HR, audit-logged, filtered).
- **C. 201 file** (`onboarding/201-file.php`): draft saving, "N of 7" banner
  (4 profile items + mandatory doc types), submit gated, day-1/3/7 reminders,
  HR watchlist (remind / extend +7d, reminder-only deadline), `document_types`
  catalogue, authorized `documents/download.php` gate. Skeleton employee
  auto-created if an account ever reaches 201 without one (killed a logout loop).
- **D. User Management** (Super Admin only, `user-management/`): search/filter,
  add (with names + linked employee), edit, activate/deactivate, unlock, reset
  login (→ number password + FIRST_LOGIN, 201/docs kept), reopen 201.
  Self-deactivate/demote blocked. Old `settings/users.php` redirects here.
- **E. Dashboard**: no title/greeting, date only; Activity & Announcements hero
  with memo attachments (`dashboard/memo.php` gate); employees see hero only.
- **F. Header/sidebar/profile**: bell dropdown with unread badge; account block
  links to profile; sidebar parents expand on click (no arrows, persisted);
  profile has avatar upload, 201 progress, missing-info banner, password change.
- **G. Attendance**: Excel→CSV import + template, 3+ lates/30d warnings +
  notices. **Performance**: HR schedules dates, Department Heads conduct
  (server-side gated), 90-day tardiness shown + factored. **Documents**: types
  configurable. **Recruitment/Positions**: `openings` headcount, filled-vs-open.
- **H. Removed from UI**: `payroll/`, `payslips/`, `reports/` deleted (nav,
  guards, Ctrl+K, quick actions, manual). **Tables + data kept in DB**
  (user decision). Final sidebar: Dashboard, Employees, Departments, Positions,
  Recruitment, Attendance, Leave, Performance, Documents, User Management
  (Super), Settings (+ System Manual, kept deliberately).
- **Extras**: Dept Heads scoped to own department (list/view/search, via own
  employee record — `departments.head_id` unused); 201 has education +
  experience sections (optional); FTP deploy script (`deploy/ftp-deploy.php`,
  env creds, skips `config/config.php` + uploads); `migrate-live.php` web
  migration runner (TOKEN-PROTECTED — never commit it).

## Migrations (`database/migrations/`, run in order, preserve data)
001 auth columns + backfill · 002 RESIGNED · 003 doc types + onboarding tag ·
004 memo column · 005 openings + review_date · 006 education/experience.
Local: `php database/migrations/migrate.php`. Decisions: passwords kept,
existing → ACTIVE + 201-complete, payroll tables kept.

## Live (InfinityFree)
- Domain: `scasci-hrms.rf.gd`, docroot `/htdocs`, app at root.
- Deploy: set `FTP_HOST/PORT/USER/PASS/PATH` env, run
  `php deploy/ftp-deploy.php --skip-unchanged`. Never uploads `config/config.php`.
- Live DB configured in server-side `config/config.php` (untracked).
- Had a transient 502 (host backend, self-recovered). FTP stayed fine.

## Still open / watch next time
- [ ] Run `migrate-live.php?token=…` for 006 if not done, then DELETE the file
  from `/htdocs` (token is in chat history, not in repo).
- [ ] Account `2026-0010` (PENDING_201): skeleton name came from email prefix —
  fix via Employees → Edit.
- [ ] Attendance grid + Performance list still show all departments to heads
  (Employees module is scoped; user hasn't asked for these yet).
- [ ] `database.sql` + `README.md` are git-ignored (pre-existing); fresh clones
  need them copied in. `config/config.php` untracked (see `config.example.php`).
