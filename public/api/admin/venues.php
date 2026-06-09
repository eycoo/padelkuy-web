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
require_once __DIR__ . '/../../../lib/session.php';
require_once __DIR__ . '/../../../lib/venues.php';

$method = $_SERVER['REQUEST_METHOD'];
require_admin(db());

$id = (int) ($_GET['id'] ?? 0);

try {
    switch ($method) {
        case 'GET':
            if ($id > 0) {
                $v = getVenue(db(), $id);
                $v ? send_json($v) : send_error('Venue not found', 404);
            } else {
                send_json(listVenues(db()));
            }
            return;

        case 'POST':
            $newId = createVenue(db(), read_body());
            send_json(getVenue(db(), $newId), 201);
            return;

        case 'PUT':
            if ($id <= 0) {
                send_error('id is required', 422);
                return;
            }
            if (!updateVenue(db(), $id, read_body())) {
                send_error('Venue not found', 404);
                return;
            }
            send_json(getVenue(db(), $id));
            return;

        case 'DELETE':
            if ($id <= 0) {
                send_error('id is required', 422);
                return;
            }
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
