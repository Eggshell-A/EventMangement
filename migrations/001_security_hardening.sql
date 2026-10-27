-- Existing installations: select your EventLink database, then import this file once.
-- Safe to re-run. No existing users, events or bookings are changed.
CREATE TABLE IF NOT EXISTS auth_rate_limits (
  bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_started BIGINT UNSIGNED NOT NULL,
  KEY idx_auth_limits_window (window_started)
) ENGINE=InnoDB;
