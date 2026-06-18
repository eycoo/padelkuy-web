<?php
// Admin venue management. All methods require an admin session.
//   GET    /api/admin/venues.php            -> list all venues
//   GET    /api/admin/venues.php?id=N       -> one venue
//   POST   /api/admin/venues.php            body: { name, city, price_per_hour, tag?, image_path? }
//   PUT    /api/admin/venues.php?id=N       body: same fields
//   DELETE /api/admin/venues.php?id=N
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/http.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/ownership.php';
require_once __DIR__ . '/../../../lib/session.php';
require_once __DIR__ . '/../../../lib/venues.php';

$method = $_SERVER['REQUEST_METHOD'];
// Every admin here is scoped to their own venue (ADR-0006).
$adminId = require_admin(db());

$id = (int) ($_GET['id'] ?? 0);

try {
    switch ($method) {
        case 'GET':
            if ($id > 0) {
                require_venue_owner(db(), $id);
                $v = getVenue(db(), $id);
                $v ? send_json($v) : send_error('Venue not found', 404);
            } else {
                // An admin lists only their own venue (0 or 1 of them).
                $owned = ownedVenueId(db(), $adminId);
                send_json($owned === null ? [] : [getVenue(db(), $owned)]);
            }
            return;

        case 'POST':
            if (ownedVenueId(db(), $adminId) !== null) {
                send_error('You already own a venue', 409);
                return;
            }
            $body = read_body();
            $newId = createVenue(db(), $body, $adminId);
            if (is_array($body['facilities'] ?? null)) {
                setFacilities(db(), $newId, $body['facilities']);
            }
            if (is_array($body['images'] ?? null)) {
                setImages(db(), $newId, $body['images']);
            }
            send_json(getVenue(db(), $newId), 201);
            return;

        case 'PUT':
            if ($id <= 0) {
                send_error('id is required', 422);
                return;
            }
            require_venue_owner(db(), $id);
            $body = read_body();
            if (!updateVenue(db(), $id, $body)) {
                send_error('Venue not found', 404);
                return;
            }
            if (is_array($body['facilities'] ?? null)) {
                setFacilities(db(), $id, $body['facilities']);
            }
            if (is_array($body['images'] ?? null)) {
                setImages(db(), $id, $body['images']);
            }
            send_json(getVenue(db(), $id));
            return;

        case 'DELETE':
            if ($id <= 0) {
                send_error('id is required', 422);
                return;
            }
            require_venue_owner(db(), $id);
            deleteVenue(db(), $id)
                ? send_json(['ok' => true])
                : send_error('Venue not found', 404);
            return;

        default:
            send_error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 422);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
