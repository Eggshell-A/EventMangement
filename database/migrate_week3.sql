-- Week 3 columns for an existing Fairboard database.
-- School-email verification and the creator plan that drives the fee label.
-- Safe to re-run: the apply step checks information_schema before each change.

ALTER TABLE users
  ADD COLUMN is_school_email TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN school_email_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN verified_at DATETIME NULL,
  ADD COLUMN verified_by INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS creator_plans (
  user_id INT UNSIGNED NOT NULL,
  organisation_name VARCHAR(120) NOT NULL,
  plan_code ENUM('verified_society','external_monthly') NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_creator_plans_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
