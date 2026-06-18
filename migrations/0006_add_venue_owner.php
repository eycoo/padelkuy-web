<?php
// ADR-0006 live migration — additive + idempotent (safe to re-run). Brings an
// existing populated DB (e.g. Railway, already holding venues/courts/bookings)
// up to the per-venue-admin model without dropping anything:
//   1. add venues.owner_id (+ FK)            — the ownership column
//   2. backfill venues.main_image_path        — = image_path where NULL (image parity)
//   3. ensure 4 admin accounts exist          — admin@ + admin2..4@ (all admin123)
//   4. assign each venue an owner             — only where owner_id IS NULL
//   5. give every court a default schedule    — everyday 07:00-21:00 @ venue price,
//                                               only for courts that have none
//
// Run against the target DB (point config/db.php's env at it). For Railway use
// the MySQL service PUBLIC proxy (internal *.railway.internal won't resolve
// locally):
//   DB_HOST=<proxy domain> DB_PORT=<proxy port> DB_NAME=railway \
//   DB_USER=root DB_PASS=<pw> php migrations/0006_add_venue_owner.php
require_once __DIR__ . '/../config/db.php';

// admin123 — same bcrypt hash used in seed.sql.
const ADMIN_HASH = '$2y$10$1ZrA0A2looh9pT13XPD6a.fTpZqJ9v/oDDcYAHK1yGERATNOS/B76';
const ADMINS = [
    ['Admin G Club',      'admin@padelkuy.test'],
    ['Admin Fote',        'admin2@padelkuy.test'],
    ['Admin Padel First', 'admin3@padelkuy.test'],
    ['Admin Hobi',        'admin4@padelkuy.test'],
];

$pdo  = db();
$name = getenv('DB_NAME') ?: 'padelkuy';

$hasColumn = function (string $table, string $column) use ($pdo, $name): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$name, $table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};

// 1. owner_id column + FK ----------------------------------------------------
if (!$hasColumn('venues', 'owner_id')) {
    $pdo->exec('ALTER TABLE venues ADD COLUMN owner_id INT NULL');
    $pdo->exec(
        'ALTER TABLE venues ADD CONSTRAINT fk_venues_owner
         FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL'
    );
    echo "1. added venues.owner_id + FK\n";
} else {
    echo "1. venues.owner_id present\n";
}

// 2. main_image_path column + backfill --------------------------------------
if (!$hasColumn('venues', 'main_image_path')) {
    $pdo->exec('ALTER TABLE venues ADD COLUMN main_image_path VARCHAR(255) NULL');
    echo "2. added venues.main_image_path\n";
}
$n = $pdo->exec('UPDATE venues SET main_image_path = image_path
                 WHERE main_image_path IS NULL AND image_path IS NOT NULL');
echo "2. backfilled main_image_path on $n venue(s)\n";

// 3. ensure the four admin accounts exist -----------------------------------
$findUser = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$insUser  = $pdo->prepare(
    "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')"
);
$adminIds = [];
foreach (ADMINS as [$adminName, $email]) {
    $findUser->execute([$email]);
    $id = $findUser->fetchColumn();
    if ($id === false) {
        $insUser->execute([$adminName, $email, ADMIN_HASH]);
        $id = (int) $pdo->lastInsertId();
        echo "3. created admin $email (#$id)\n";
    } else {
        $id = (int) $id;
        // Make sure an existing account is actually an admin.
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$id]);
    }
    $adminIds[] = $id;
}

// 4. assign owners to unowned venues (by venue id order) ---------------------
$venues = $pdo->query('SELECT id FROM venues ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$setOwner = $pdo->prepare('UPDATE venues SET owner_id = ? WHERE id = ? AND owner_id IS NULL');
foreach (array_values($venues) as $i => $vid) {
    if (!isset($adminIds[$i])) {
        echo "4. venue #$vid left unowned (only " . count($adminIds) . " admins)\n";
        continue;
    }
    if ($setOwner->execute([$adminIds[$i], (int) $vid]) && $setOwner->rowCount() > 0) {
        echo "4. venue #$vid -> owner #{$adminIds[$i]}\n";
    }
}

// 5. default schedule for any court with none -------------------------------
$courts = $pdo->query(
    'SELECT c.id, v.price_per_hour
     FROM courts c JOIN venues v ON v.id = c.venue_id
     WHERE NOT EXISTS (SELECT 1 FROM court_schedules s WHERE s.court_id = c.id)'
)->fetchAll();
$insSched = $pdo->prepare(
    "INSERT INTO court_schedules (court_id, day_band, start_hour, end_hour, price)
     VALUES (?, 'everyday', 7, 21, ?)"
);
foreach ($courts as $c) {
    $insSched->execute([(int) $c['id'], (int) $c['price_per_hour']]);
}
echo '5. seeded default schedule on ' . count($courts) . " court(s)\n";

echo "done.\n";
