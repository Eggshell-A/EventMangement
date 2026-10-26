-- Fairboard redesign migration. Safe to run more than once on MySQL 8.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
  ADD COLUMN IF NOT EXISTS failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active,
  ADD COLUMN IF NOT EXISTS last_failed_login_at DATETIME NULL AFTER failed_login_count;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS original_capacity INT UNSIGNED NULL AFTER capacity,
  ADD COLUMN IF NOT EXISTS refill_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER original_capacity;

UPDATE events SET original_capacity = capacity WHERE original_capacity IS NULL OR original_capacity = 0;
ALTER TABLE events MODIFY original_capacity INT UNSIGNED NOT NULL;
