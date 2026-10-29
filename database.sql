-- Local XAMPP: create the database, select it, then run this file.
-- CREATE DATABASE IF NOT EXISTS eventlink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE eventlink;
-- Aiven has one database, defaultdb. Select it and run the statements below.
-- Do not CREATE DATABASE there.

CREATE TABLE roles (
  role_id TINYINT NOT NULL,
  code VARCHAR(32) NOT NULL,
  label VARCHAR(64) NOT NULL,
  PRIMARY KEY (role_id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB;

INSERT INTO roles (role_id, code, label) VALUES
  (1, 'user', 'User'),
  (2, 'creator', 'Event creator'),
  (3, 'staff', 'Staff'),
  (4, 'admin', 'Admin');

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','creator','staff','admin') NOT NULL DEFAULT 'user',
  is_school_email TINYINT(1) NOT NULL DEFAULT 0,
  school_email_verified TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  verified_by INT UNSIGNED NULL,
  email_verified_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  auth_version INT UNSIGNED NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE creator_plans (
  user_id INT UNSIGNED NOT NULL,
  organisation_name VARCHAR(120) NOT NULL,
  verification_reference VARCHAR(120) NULL,
  plan_code ENUM('verified_society','external_monthly') NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_creator_plans_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE staff_assignments (
  assignment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_user_id INT UNSIGNED NOT NULL,
  creator_user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (assignment_id),
  UNIQUE KEY uq_staff_creator (staff_user_id, creator_user_id),
  KEY idx_staff_assignments_creator (creator_user_id),
  CONSTRAINT fk_staff_assignments_staff FOREIGN KEY (staff_user_id) REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_staff_assignments_creator FOREIGN KEY (creator_user_id) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE events (
  event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organiser_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  category VARCHAR(32) NOT NULL DEFAULT 'Community',
  artwork VARCHAR(32) NOT NULL DEFAULT 'city',
  image_path VARCHAR(80) NULL,
  description TEXT NOT NULL,
  start_time DATETIME NOT NULL,
  location VARCHAR(200) NOT NULL,
  capacity INT UNSIGNED NOT NULL,
  original_capacity INT UNSIGNED NOT NULL DEFAULT 0,
  refill_count INT UNSIGNED NOT NULL DEFAULT 0,
  poster_path VARCHAR(255) NULL,
  social_url VARCHAR(500) NULL,
  status ENUM('draft','pending','approved','rejected','cancelled','removed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_organiser FOREIGN KEY (organiser_id) REFERENCES users(user_id) ON DELETE RESTRICT,
  KEY idx_events_public (status, start_time), KEY idx_events_organiser (organiser_id)
) ENGINE=InnoDB;

CREATE TABLE bookings (
  booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  checked_in TINYINT(1) NOT NULL DEFAULT 0,
  checked_in_at DATETIME NULL,
  checked_in_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT uq_booking_event_user UNIQUE (event_id, user_id),
  KEY idx_bookings_event (event_id), KEY idx_bookings_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE waiting_list (
  entry_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_waiting_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
  CONSTRAINT fk_waiting_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT uq_waiting_event_user UNIQUE (event_id, user_id),
  KEY idx_waiting_fifo (event_id, joined_at, entry_id)
) ENGINE=InnoDB;

-- Staff and admin are not on the public registration form.
-- UPDATE users SET role = 'admin' WHERE email = 'person@example.com';
-- UPDATE users SET role = 'staff' WHERE email = 'person@example.com';

-- Additional account, audit, notification and invoice tables.
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

CREATE TABLE IF NOT EXISTS user_mfa (
 user_id INT UNSIGNED PRIMARY KEY,
 secret_cipher TEXT NOT NULL,
 last_counter BIGINT NOT NULL DEFAULT -1,
 recovery_hashes TEXT NOT NULL,
 enabled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS staff_invites (
 invite_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(254) NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 invited_by INT UNSIGNED NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
