-- Fairboard redesign migration. Safe to run more than once on MySQL 8.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
  ADD COLUMN IF NOT EXISTS failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active,
  ADD COLUMN IF NOT EXISTS last_failed_login_at DATETIME NULL AFTER failed_login_count;
