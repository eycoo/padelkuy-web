<?php
// Admin court management. All methods require an admin session.
//   GET    /api/admin/courts.php?venue_id=N   -> that venue's courts
//   POST   /api/admin/courts.php              body: { venue_id, label, type? }
//   PUT    /api/admin/courts.php?id=N         body: { label, type? }
//   DELETE /api/admin/courts.php?id=N
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/http.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/session.php';
require_once __DIR__ . '/../../../lib/courts.php';

$method = $_SERVER['REQUEST_METHOD'];
require_admin(db());

try {
    switch ($method) {
        case 'GET':
            $venueId = (int) ($_GET['venue_id'] ?? 0);
            if ($venueId <= 0) {
                send_error('venue_id is required', 422);
                return;
            }
            send_json(listCourts(db(), $venueId));
            return;

        case 'POST':
            $body = read_body();
            $venueId = (int) ($body['venue_id'] ?? 0);
            if ($venueId <= 0) {
                send_error('venue_id is required', 422);
                return;
            }
            $id = createCourt(db(), $venueId, (string) ($body['label'] ?? ''), (string) ($body['type'] ?? 'indoor'));
            send_json(['id' => $id], 201);
            return;

        case 'PUT':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                send_error('id is required', 422);
                return;
            }
            $body = read_body();
            updateCourt(db(), $id, (string) ($body['label'] ?? ''), (string) ($body['type'] ?? 'indoor'))
                ? send_json(['ok' => true])
                : send_error('Court not found', 404);
            return;

        case 'DELETE':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                send_error('id is required', 422);
                return;
            }
            deleteCourt(db(), $id)
                ? send_json(['ok' => true])
                : send_error('Court not found', 404);
            return;

        default:
            send_error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 422);
} catch (PDOException $e) {
    // e.g. venue_id references a venue that doesn't exist
    send_error('Invalid court', 422);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
