-- Migration 002: Part B — RESIGNED status + onboarding deadline default
-- Adds RESIGNED to employees.status enum (spec: resigned OR terminated deactivates).

ALTER TABLE `employees`
  MODIFY COLUMN `status` ENUM('ACTIVE','ON_LEAVE','TERMINATED','PROBATION','RESIGNED') NOT NULL DEFAULT 'ACTIVE';
