CREATE DATABASE IF NOT EXISTS eventlink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eventlink;

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('attendee','organiser','admin') NOT NULL DEFAULT 'attendee',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE events (
  event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organiser_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  start_time DATETIME NOT NULL,
  location VARCHAR(200) NOT NULL,
  capacity INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected','removed') NOT NULL DEFAULT 'pending',
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

-- Create an admin manually after registration (replace address):
-- UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
