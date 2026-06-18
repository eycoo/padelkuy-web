<?php
// POST /api/register_admin.php
//   body: { name, email, password, venue_name, city, price_per_hour }
// Creates an admin user AND their venue (owner_id = the new user) in one
// transaction, then logs them in (ADR-0006). Replaces the old fake client-side
// "registration". Returns 201 { id, name, email, role, venue_id } + a session.
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/http.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/venues.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed', 405);
    return;
}

$body = read_body();
$pdo  = db();

$pdo->beginTransaction();
try {
    $userId = registerAdmin(
        $pdo,
        (string) ($body['name'] ?? ''),
        (string) ($body['email'] ?? ''),
        (string) ($body['password'] ?? '')
    );

    $venueId = createVenue($pdo, [
        'name'           => (string) ($body['venue_name'] ?? ''),
        'city'           => (string) ($body['city'] ?? ''),
        'price_per_hour' => (int) ($body['price_per_hour'] ?? 0),
    ], $userId);

    $pdo->commit();
} catch (InvalidArgumentException $e) {
    $pdo->rollBack();
    send_error($e->getMessage(), 422);
    return;
} catch (DuplicateEmailException $e) {
    $pdo->rollBack();
    send_error($e->getMessage(), 409);
    return;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    send_error('Internal server error', 500);
    return;
}

// Log the new admin in, mirroring login.php.
session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;

send_json([
    'id'       => $userId,
    'name'     => trim((string) ($body['name'] ?? '')),
    'email'    => trim((string) ($body['email'] ?? '')),
    'role'     => 'admin',
    'venue_id' => $venueId,
], 201);
