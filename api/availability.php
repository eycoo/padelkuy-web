<?php
// GET /api/availability.php?venue_id=1&date=YYYY-MM-DD
// Returns each court of the venue with its derived hour grid.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/availability.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed', 405);
    return;
}

$venue_id = isset($_GET['venue_id']) ? (int) $_GET['venue_id'] : 0;
$date     = isset($_GET['date']) ? trim((string) $_GET['date']) : '';

if ($venue_id <= 0) {
    send_error('venue_id is required', 422);
    return;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    send_error('date must be YYYY-MM-DD', 422);
    return;
}

try {
    send_json([
        'venue_id' => $venue_id,
        'date'     => $date,
        'courts'   => getVenueAvailability(db(), $venue_id, $date),
    ]);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
