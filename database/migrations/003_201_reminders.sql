-- Migration 003: Part C — configurable document types, applicant onboarding tag.
CREATE TABLE IF NOT EXISTS `document_types` (
  `id` CHAR(36) PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `mandatory` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `applicants`
  ADD COLUMN IF NOT EXISTS `onboarding_tag` VARCHAR(20) NOT NULL DEFAULT 'NONE';

-- Default catalogue: 3 mandatory + 4 optional (required-items total = 4 profile + 3 docs = 7).
INSERT INTO `document_types` (`id`,`name`,`mandatory`) VALUES
('dt000001-0000-0000-0000-000000000001','Birth Certificate',1),
('dt000002-0000-0000-0000-000000000002','Valid ID',1),
('dt000003-0000-0000-0000-000000000003','NBI Clearance',1),
('dt000004-0000-0000-0000-000000000004','TOR / Diploma',0),
('dt000005-0000-0000-0000-000000000005','Medical Certificate',0),
('dt000006-0000-0000-0000-000000000006','Certificate of Employment',0),
('dt000007-0000-0000-0000-000000000007','Resume / CV',0)
ON DUPLICATE KEY UPDATE `mandatory`=VALUES(`mandatory`);

-- Applicants already HIRED get the onboarding tag (triggers mandatory upload on conversion).
UPDATE `applicants` SET `onboarding_tag`='ONBOARDING' WHERE `status`='HIRED' AND (`onboarding_tag` IS NULL OR `onboarding_tag`='NONE');
