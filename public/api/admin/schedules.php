<?php
// Admin court-schedule management. All methods require an admin session.
//   GET /api/admin/schedules.php?court_id=N   -> that court's schedules
//   PUT /api/admin/schedules.php?court_id=N   body: { schedules: [{day,start,end,price}] }
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/http.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/session.php';
require_once __DIR__ . '/../../../lib/schedules.php';

$method = $_SERVER['REQUEST_METHOD'];
require_admin(db());

$courtId = (int) ($_GET['court_id'] ?? 0);

try {
    switch ($method) {
        case 'GET':
            if ($courtId <= 0) {
                send_error('court_id is required', 422);
                return;
            }
            send_json(listSchedules(db(), $courtId));
            return;

        case 'PUT':
            if ($courtId <= 0) {
                send_error('court_id is required', 422);
                return;
            }
            $rows = read_body()['schedules'] ?? [];
            setSchedules(db(), $courtId, is_array($rows) ? $rows : []);
            send_json(listSchedules(db(), $courtId));
            return;

        default:
            send_error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 422);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
