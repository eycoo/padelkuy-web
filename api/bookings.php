<?php
// POST /api/bookings.php  body: { court_id, date, start_hour, end_hour }
// Requires a logged-in session. Returns the created booking with price.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/bookings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed', 405);
    return;
}

$user_id = require_login();
$body = read_body();

$court_id = (int) ($body['court_id'] ?? 0);
$date     = (string) ($body['date'] ?? '');
$start    = (int) ($body['start_hour'] ?? 0);
$end      = (int) ($body['end_hour'] ?? 0);

if ($court_id <= 0) {
    send_error('court_id is required', 422);
    return;
}

try {
    $id = createBooking(db(), $user_id, $court_id, $date, $start, $end);
    send_json(getBooking(db(), $id), 201);
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 422);
} catch (BookingConflictException $e) {
    send_error($e->getMessage(), 409);
} catch (PDOException $e) {
    // e.g. court_id references a court that doesn't exist
    send_error('Invalid booking', 422);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
