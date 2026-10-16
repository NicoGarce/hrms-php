-- Migration 005: Part G — headcount openings + scheduled review dates.
ALTER TABLE `recruitment_jobs`
  ADD COLUMN IF NOT EXISTS `openings` INT NOT NULL DEFAULT 1;
ALTER TABLE `positions`
  ADD COLUMN IF NOT EXISTS `openings` INT NOT NULL DEFAULT 1;
ALTER TABLE `performance_reviews`
  ADD COLUMN IF NOT EXISTS `review_date` DATE NULL;
