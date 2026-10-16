-- Migration 001: Employee Number login + account states + rate limiting
-- Preserves existing data. Idempotent-ish (guarded by migrate.php).
-- Decisions applied: keep current passwords, all existing accounts => ACTIVE.

-- 1) users: employee_number / account_state / lockout / last login
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `employee_number` VARCHAR(20) NULL UNIQUE,
  ADD COLUMN IF NOT EXISTS `account_state` ENUM('FIRST_LOGIN','PENDING_201','ACTIVE','DEACTIVATED') NOT NULL DEFAULT 'ACTIVE',
  ADD COLUMN IF NOT EXISTS `failed_attempts` INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `last_login` DATETIME NULL;

-- 2) employee_profiles: minimal 201-file tracking (full UX in Part C)
ALTER TABLE `employee_profiles`
  ADD COLUMN IF NOT EXISTS `sss_no` VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS `tin_no` VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS `philhealth_no` VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS `pagibig_no` VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS `civil_status` VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS `nationality` VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS `is_201_submitted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `submitted_201_at` DATETIME NULL,
  ADD COLUMN IF NOT EXISTS `onboarding_deadline` DATE NULL,
  ADD COLUMN IF NOT EXISTS `draft_201` JSON NULL;

-- 3) Backfill employee_number YYYY-NNNN for rows lacking one (year = current year).
-- Ordered by created_at so seeded admin (oldest) gets YYYY-0001.
-- MySQL 8 / MariaDB compatible via user variable.
SET @yr := YEAR(CURDATE());
SET @n := 0;

-- Find current max sequence for this year to avoid collisions on re-run
-- (e.g. 2026-0008 exists => next is 0009).
SELECT IFNULL(MAX(CAST(SUBSTRING(`employee_number`, 6) AS UNSIGNED)), 0) INTO @n
FROM `users` WHERE `employee_number` REGEXP CONCAT(@yr, '-[0-9]{4}');

UPDATE `users`
SET `employee_number` = CONCAT(@yr, '-', LPAD((@n := @n + 1), 4, '0'))
WHERE `employee_number` IS NULL OR `employee_number` = ''
ORDER BY `created_at` ASC, `email` ASC;

-- 4) Align legacy employee codes (EMP001...) with the linked user's number.
UPDATE `employees` e
JOIN `users` u ON u.`id` = e.`user_id`
SET e.`employee_code` = u.`employee_number`
WHERE e.`employee_code` LIKE 'EMP%' OR e.`employee_code` != u.`employee_number`;

-- 5) Existing accounts: keep passwords, mark ACTIVE (per confirmed decision),
-- clear any lockout counters.
UPDATE `users` SET `account_state` = 'ACTIVE', `failed_attempts` = 0, `locked_until` = NULL
WHERE `account_state` IS NULL OR `account_state` = '';

UPDATE `users` SET `account_state` = 'ACTIVE' WHERE `deleted_at` IS NULL;

-- 6) Existing employees treated as 201-complete (per confirmed decision).
UPDATE `employee_profiles` SET `is_201_submitted` = 1, `submitted_201_at` = NOW()
WHERE `is_201_submitted` = 0;
