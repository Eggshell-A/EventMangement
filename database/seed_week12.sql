-- Week 1-2 seed. Run after database.sql or database/migrate_week12.sql.
-- Re-running does not duplicate rows.
-- Password for every seed account is Fairboard1! (bcrypt cost 12). This is a local test password, not a hosting password.

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

INSERT INTO users (name, email, password_hash, role)
SELECT 'User', 'user@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'user' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'user@fairboard.test');

INSERT INTO users (name, email, password_hash, role)
SELECT 'Event creator', 'creator@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'creator' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'creator@fairboard.test');

INSERT INTO users (name, email, password_hash, role)
SELECT 'Staff', 'staff@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'staff' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'staff@fairboard.test');

INSERT INTO users (name, email, password_hash, role)
SELECT 'Admin', 'admin@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'admin' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@fairboard.test');

INSERT INTO staff_assignments (staff_user_id, creator_user_id)
SELECT s.user_id, c.user_id
FROM users s
JOIN users c ON c.email = 'creator@fairboard.test'
WHERE s.email = 'staff@fairboard.test'
  AND NOT EXISTS (
    SELECT 1 FROM staff_assignments a
    WHERE a.staff_user_id = s.user_id AND a.creator_user_id = c.user_id
  );
