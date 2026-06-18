-- Seed for managed hosts (Railway/etc): same as seed.sql but WITHOUT `USE`.
-- Run after schema.railway.sql against the provider's database.

-- One admin owns one venue (ADR-0006). Four venue admins (all password
-- admin123) + one demo customer, inserted before the venues so owner_id can
-- reference them.
INSERT INTO users (name, email, password_hash, role) VALUES
  ('Admin G Club',     'admin@padelkuy.test',  '$2y$10$1ZrA0A2looh9pT13XPD6a.fTpZqJ9v/oDDcYAHK1yGERATNOS/B76', 'admin'),
  ('Admin Fote',       'admin2@padelkuy.test', '$2y$10$1ZrA0A2looh9pT13XPD6a.fTpZqJ9v/oDDcYAHK1yGERATNOS/B76', 'admin'),
  ('Admin Padel First','admin3@padelkuy.test', '$2y$10$1ZrA0A2looh9pT13XPD6a.fTpZqJ9v/oDDcYAHK1yGERATNOS/B76', 'admin'),
  ('Admin Hobi',       'admin4@padelkuy.test', '$2y$10$1ZrA0A2looh9pT13XPD6a.fTpZqJ9v/oDDcYAHK1yGERATNOS/B76', 'admin'),
  ('Demo User',        'user@padelkuy.test',   '$2y$10$rrY6uRPmTl9aIU89zF9LdOKhA.CwYBX/lNsx.bLHHucpOK6XTtriC', 'user');

-- main_image_path mirrors the thumbnail so the customer detail hero and the
-- venue card show the same image until an admin uploads a distinct one.
INSERT INTO venues (name, city, price_per_hour, tag, description, image_path, main_image_path, owner_id) VALUES
  ('the G club Padel',      'Jakarta Selatan', 180000, 'Glass walls · Premium turf', 'Premium padel club with glass-walled courts and pro turf.', 'assets/images/court-1-image.jpeg', 'assets/images/court-1-image.jpeg', (SELECT id FROM users WHERE email = 'admin@padelkuy.test')),
  ('Fote padel and space',  'Jakarta Pusat',   150000, 'Open air · Night lights',    'Open-air courts under night lights in central Jakarta.',     'assets/images/court-2-image.jpeg', 'assets/images/court-2-image.jpeg', (SELECT id FROM users WHERE email = 'admin2@padelkuy.test')),
  ('Padel First',           'Bandung',         200000, 'AC · Spectator seats',       'Air-conditioned indoor courts with spectator seating.',      'assets/images/court-3-image.jpeg', 'assets/images/court-3-image.jpeg', (SELECT id FROM users WHERE email = 'admin3@padelkuy.test')),
  ('Hobi Padl',             'Surabaya',        170000, 'City view · Outdoor',        'Rooftop outdoor courts with a city view.',                   'assets/images/court-4-image.jpeg', 'assets/images/court-4-image.jpeg', (SELECT id FROM users WHERE email = 'admin4@padelkuy.test'));

-- Three courts per venue (no schedules -> fixed grid + venue price, ADR-0005).
INSERT INTO courts (venue_id, label)
SELECT v.id, c.label
FROM venues v
CROSS JOIN (SELECT 'A' AS label UNION ALL SELECT 'B' UNION ALL SELECT 'C') c;

-- Explicit everyday 07:00-21:00 schedule per court at the venue price (equals the
-- ADR-0005 fallback grid), so the admin editor and the customer page show the
-- same hours.
INSERT INTO court_schedules (court_id, day_band, start_hour, end_hour, price)
SELECT c.id, 'everyday', 7, 21, v.price_per_hour
FROM courts c JOIN venues v ON v.id = c.venue_id;

-- A few facility chips per venue.
INSERT INTO venue_facilities (venue_id, name)
SELECT v.id, f.name
FROM venues v
CROSS JOIN (SELECT 'Shower' AS name UNION ALL SELECT 'Parking' UNION ALL SELECT 'Cafe') f;
