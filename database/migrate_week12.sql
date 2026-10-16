-- Week 1-2 role migration for an existing EventLink database.
-- Run inside the already selected database (Aiven defaultdb, or local eventlink).
-- Safe to re-run on MariaDB 10.4 and MySQL 8. A crash between statements leaves a wider enum. Run the file again.
-- Order is widen, map attendee->user and organiser->creator, then narrow. The events.organiser_id column is not renamed.

ALTER TABLE users
  MODIFY role ENUM('attendee','organiser','admin','user','creator','staff') NOT NULL DEFAULT 'user';

UPDATE users SET role = 'user' WHERE role = 'attendee';
UPDATE users SET role = 'creator' WHERE role = 'organiser';

ALTER TABLE users
  MODIFY role ENUM('user','creator','staff','admin') NOT NULL DEFAULT 'user';

CREATE TABLE IF NOT EXISTS roles (
  role_id TINYINT NOT NULL,
  code VARCHAR(32) NOT NULL,
  label VARCHAR(64) NOT NULL,
  PRIMARY KEY (role_id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB;

INSERT INTO roles (role_id, code, label)
SELECT 1, 'user', 'User' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_id = 1 OR code = 'user');

INSERT INTO roles (role_id, code, label)
SELECT 2, 'creator', 'Event creator' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_id = 2 OR code = 'creator');

INSERT INTO roles (role_id, code, label)
SELECT 3, 'staff', 'Staff' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_id = 3 OR code = 'staff');

INSERT INTO roles (role_id, code, label)
SELECT 4, 'admin', 'Admin' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_id = 4 OR code = 'admin');

CREATE TABLE IF NOT EXISTS staff_assignments (
  assignment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_user_id INT UNSIGNED NOT NULL,
  creator_user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (assignment_id),
  UNIQUE KEY uq_staff_creator (staff_user_id, creator_user_id),
  KEY idx_staff_assignments_creator (creator_user_id),
  CONSTRAINT fk_staff_assignments_staff FOREIGN KEY (staff_user_id) REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_staff_assignments_creator FOREIGN KEY (creator_user_id) REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
