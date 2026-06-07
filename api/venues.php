<?php
// GET /api/venues.php           -> all venues as JSON
// GET /api/venues.php?city=...  -> venues filtered by city
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/venues.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed', 405);
    return;
}

try {
    $city = isset($_GET['city']) ? trim((string) $_GET['city']) : null;
    send_json(listVenues(db(), $city));
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
