-- Migration 006: 201 file — educational attainment + career experience (optional sections).
CREATE TABLE IF NOT EXISTS `employee_education` (
  `id` CHAR(36) PRIMARY KEY,
  `employee_id` CHAR(36) NOT NULL,
  `level` VARCHAR(30) NOT NULL DEFAULT 'College',
  `school` VARCHAR(150) NOT NULL,
  `course` VARCHAR(150) NULL,
  `year_graduated` VARCHAR(9) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_edu_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_experience` (
  `id` CHAR(36) PRIMARY KEY,
  `employee_id` CHAR(36) NOT NULL,
  `company` VARCHAR(150) NOT NULL,
  `position` VARCHAR(100) NOT NULL,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  INDEX `idx_exp_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
