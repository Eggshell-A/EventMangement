CREATE TABLE IF NOT EXISTS auth_rate_limits (
 bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 0, window_started BIGINT UNSIGNED NOT NULL,
 KEY idx_auth_limits_window(window_started)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS account_tokens (
 token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 purpose ENUM('reset','verify') NOT NULL, token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL, UNIQUE KEY uq_user_purpose(user_id,purpose),
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_log (
 audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_id INT UNSIGNED NULL,
 action VARCHAR(64) NOT NULL, subject_id BIGINT UNSIGNED NULL, ip_hash CHAR(64) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_audit_time(created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS notifications (
 notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 event_id INT UNSIGNED NULL, message VARCHAR(500) NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_notifications_user(user_id,is_read), FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS creator_invoices (
 invoice_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 billing_month DATE NOT NULL, amount_cents INT UNSIGNED NOT NULL DEFAULT 8000,
 status ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid', reference VARCHAR(120) NULL,
 recorded_by INT UNSIGNED NULL, recorded_at DATETIME NULL,
 UNIQUE KEY uq_invoice_month(user_id,billing_month), FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
