-- Week 3 sample organisers and approved events. Re-running does not duplicate rows.
-- Every sample account uses the same local test password as the week 2 seed: Fairboard1!

INSERT INTO users (name, email, password_hash, role, is_school_email, school_email_verified)
SELECT 'Harbour Collective', 'external@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'creator', 0, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'external@fairboard.test');

INSERT INTO users (name, email, password_hash, role, is_school_email, school_email_verified)
SELECT 'Pending Society', 'pending@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'creator', 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'pending@fairboard.test');

INSERT INTO users (name, email, password_hash, role, is_school_email, school_email_verified)
SELECT 'Unverified Society', 'unverified@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'creator', 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'unverified@fairboard.test');

INSERT INTO users (name, email, password_hash, role)
SELECT 'Guest Two', 'guest2@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'user' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'guest2@fairboard.test');

INSERT INTO users (name, email, password_hash, role)
SELECT 'Filler One', 'filler1@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'user' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'filler1@fairboard.test');

INSERT INTO users (name, email, password_hash, role)
SELECT 'Filler Two', 'filler2@fairboard.test', '$2y$12$N3HnUNA.TS8EUNz3Pz6bTe8UtZiSjJaeElRWor1VBZEHyl7ZfWvHa', 'user' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'filler2@fairboard.test');

UPDATE users
SET is_school_email = 1, school_email_verified = 1, verified_at = COALESCE(verified_at, NOW())
WHERE email = 'creator@fairboard.test';

INSERT INTO creator_plans (user_id, organisation_name, plan_code)
SELECT user_id, 'North Campus Music Society', 'verified_society' FROM users WHERE email = 'creator@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM creator_plans p JOIN users u ON u.user_id = p.user_id WHERE u.email = 'creator@fairboard.test');

INSERT INTO creator_plans (user_id, organisation_name, plan_code)
SELECT user_id, 'Harbour Collective', 'external_monthly' FROM users WHERE email = 'external@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM creator_plans p JOIN users u ON u.user_id = p.user_id WHERE u.email = 'external@fairboard.test');

INSERT INTO creator_plans (user_id, organisation_name, plan_code)
SELECT user_id, 'Pending Society', 'external_monthly' FROM users WHERE email = 'pending@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM creator_plans p JOIN users u ON u.user_id = p.user_id WHERE u.email = 'pending@fairboard.test');

INSERT INTO creator_plans (user_id, organisation_name, plan_code)
SELECT user_id, 'Unverified Society', 'external_monthly' FROM users WHERE email = 'unverified@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM creator_plans p JOIN users u ON u.user_id = p.user_id WHERE u.email = 'unverified@fairboard.test');

INSERT INTO events (organiser_id, title, description, start_time, location, capacity, original_capacity, status)
SELECT u.user_id, 'Campus Acoustic Night', 'An evening of student bands and solo sets in the union hall. Doors open half an hour before the first act. One place per person.', '2026-11-07 19:00:00', 'Student Union Hall', 80, 80, 'approved'
FROM users u WHERE u.email = 'creator@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM events WHERE title = 'Campus Acoustic Night');

INSERT INTO events (organiser_id, title, description, start_time, location, capacity, original_capacity, status)
SELECT u.user_id, 'Harbour Night Market Walk', 'A guided walk through the night market with stops for street food and local makers. The group meets at the park gate. This is an external group, so the monthly label is shown and no money is collected here.', '2026-11-14 16:00:00', 'Victoria Park gate', 40, 40, 'approved'
FROM users u WHERE u.email = 'external@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM events WHERE title = 'Harbour Night Market Walk');

INSERT INTO events (organiser_id, title, description, start_time, location, capacity, original_capacity, status)
SELECT u.user_id, 'Board Game Sunday', 'Bring a game or join a table. Beginners are welcome. The session ends when the library lounge closes.', '2026-11-22 14:00:00', 'Library Lounge', 24, 24, 'approved'
FROM users u WHERE u.email = 'creator@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM events WHERE title = 'Board Game Sunday');

INSERT INTO events (organiser_id, title, description, start_time, location, capacity, original_capacity, status)
SELECT u.user_id, 'Community First Aid Workshop', 'A short practical session on cuts, burns, and how to call for help. Wear comfortable clothes. Places are limited so the tutor can watch each person.', '2026-11-29 10:00:00', 'Health Sciences Lab', 20, 20, 'approved'
FROM users u WHERE u.email = 'creator@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM events WHERE title = 'Community First Aid Workshop');

INSERT INTO events (organiser_id, title, description, start_time, location, capacity, original_capacity, status)
SELECT u.user_id, 'Winter Film Club: Shorts', 'Four short films and a discussion afterwards. The screening starts on time. This external group shows a monthly label only.', '2026-12-05 18:30:00', 'Lecture Theatre B', 100, 100, 'approved'
FROM users u WHERE u.email = 'external@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM events WHERE title = 'Winter Film Club: Shorts');

INSERT INTO events (organiser_id, title, description, start_time, location, capacity, original_capacity, status)
SELECT u.user_id, 'Charity Run Briefing', 'A full briefing for the campus charity run. This sample event is already full so the waiting list can be tried.', '2026-12-12 09:00:00', 'Sports Ground', 2, 2, 'approved'
FROM users u WHERE u.email = 'creator@fairboard.test'
AND NOT EXISTS (SELECT 1 FROM events WHERE title = 'Charity Run Briefing');

INSERT INTO bookings (event_id, user_id)
SELECT e.event_id, u.user_id FROM events e JOIN users u ON u.email = 'filler1@fairboard.test'
WHERE e.title = 'Charity Run Briefing'
AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.event_id = e.event_id AND b.user_id = u.user_id);

INSERT INTO bookings (event_id, user_id)
SELECT e.event_id, u.user_id FROM events e JOIN users u ON u.email = 'filler2@fairboard.test'
WHERE e.title = 'Charity Run Briefing'
AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.event_id = e.event_id AND b.user_id = u.user_id);

INSERT INTO waiting_list (event_id, user_id)
SELECT e.event_id, u.user_id FROM events e JOIN users u ON u.email = 'guest2@fairboard.test'
WHERE e.title = 'Charity Run Briefing'
AND NOT EXISTS (SELECT 1 FROM waiting_list w WHERE w.event_id = e.event_id AND w.user_id = u.user_id);
