-- Seed venues across cities, each with three courts (A, B, C).
-- Run after schema.sql.
USE padelkuy;

INSERT INTO venues (name, city, price_per_hour, tag, image_path) VALUES
  ('the G club Padel',      'Jakarta Selatan', 180000, 'Glass walls · Premium turf', 'assets/images/court-1-image.jpeg'),
  ('Fote padel and space',  'Jakarta Pusat',   150000, 'Open air · Night lights',    'assets/images/court-2-image.jpeg'),
  ('Padel First',           'Bandung',         200000, 'AC · Spectator seats',       'assets/images/court-3-image.jpeg'),
  ('Hobi Padl',             'Surabaya',        170000, 'City view · Outdoor',        'assets/images/court-4-image.jpeg');

-- Three courts per venue.
INSERT INTO courts (venue_id, label)
SELECT v.id, c.label
FROM venues v
CROSS JOIN (SELECT 'A' AS label UNION ALL SELECT 'B' UNION ALL SELECT 'C') c;

-- A seed admin account so the admin panel is reachable after a fresh load.
-- Login: admin@padelkuy.test / admin123  (change the password in production).
INSERT INTO users (name, email, password_hash, role) VALUES
  ('Admin', 'admin@padelkuy.test', '$2y$10$1ZrA0A2looh9pT13XPD6a.fTpZqJ9v/oDDcYAHK1yGERATNOS/B76', 'admin');
