-- Historical MariaDB migration. For this release and MySQL 8 use: php tools/migrate.php
-- Fairboard redesign migration. Retained from the team redesign history.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
  ADD COLUMN IF NOT EXISTS failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active,
  ADD COLUMN IF NOT EXISTS last_failed_login_at DATETIME NULL AFTER failed_login_count;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS original_capacity INT UNSIGNED NULL AFTER capacity,
  ADD COLUMN IF NOT EXISTS refill_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER original_capacity;

UPDATE events SET original_capacity = capacity WHERE original_capacity IS NULL OR original_capacity = 0;
ALTER TABLE events MODIFY original_capacity INT UNSIGNED NOT NULL;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS category ENUM('music','community','learning','sports','social','other') NOT NULL DEFAULT 'other' AFTER refill_count,
  ADD COLUMN IF NOT EXISTS poster_path VARCHAR(255) NULL AFTER category,
  ADD COLUMN IF NOT EXISTS social_url VARCHAR(500) NULL AFTER poster_path;

ALTER TABLE events
  MODIFY status ENUM('draft','pending','approved','rejected','cancelled','removed') NOT NULL DEFAULT 'draft';
