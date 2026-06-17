<?php
// Admin booking oversight. All methods require an admin session.
//   GET    /api/admin/bookings.php[?venue_id=N][&date=][&status=][&page=N][&limit=N]
//   DELETE /api/admin/bookings.php?id=N                            -> cancel one
// GET sends the total match count (ignoring paging) in the X-Total-Count header.
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/http.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/session.php';
require_once __DIR__ . '/../../../lib/bookings.php';
require_once __DIR__ . '/../../../lib/payments.php';

$method = $_SERVER['REQUEST_METHOD'];
require_admin(db());

try {
    switch ($method) {
        case 'GET':
            $filters = [];
            if (!empty($_GET['venue_id'])) {
                $filters['venue_id'] = (int) $_GET['venue_id'];
            }
            if (!empty($_GET['date'])) {
                $filters['date'] = (string) $_GET['date'];
            }
            if (!empty($_GET['status'])) {
                $filters['status'] = (string) $_GET['status'];
            }
            if (!empty($_GET['limit']) || !empty($_GET['page'])) {
                $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
                $page  = max(1, (int) ($_GET['page'] ?? 1));
                $filters['limit']  = $limit;
                $filters['offset'] = ($page - 1) * $limit;
            }
            header('X-Total-Count: ' . countAllBookings(db(), $filters));
            send_json(listAllBookings(db(), $filters));
            return;

        case 'DELETE':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                send_error('id is required', 422);
                return;
            }
            adminCancelBooking(db(), $id)
                ? send_json(['ok' => true])
                : send_error('Booking not found', 404);
            return;

        default:
            send_error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
