-- Migration 004: Part E — announcement memo attachments.
ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `memo_file` VARCHAR(500) NULL;
