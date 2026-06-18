<?php
// Admin bulk venue save (#50). One transactional endpoint behind the editor's
// "Simpan Perubahan Venue" button.
//   POST /api/admin/venue_save.php          create a venue from the bundle
//   PUT  /api/admin/venue_save.php?id=N      update venue N from the bundle
// Bundle: { name, city, price_per_hour, tag?, description?, image_path?,
//           main_image_path?, facilities:[], images:[],
//           courts:[{id?, label, type, schedules:[{day,start,end,price}]}] }
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/http.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/ownership.php';
require_once __DIR__ . '/../../../lib/session.php';
require_once __DIR__ . '/../../../lib/venue_save.php';

$method = $_SERVER['REQUEST_METHOD'];
$adminId = require_admin(db());

try {
    switch ($method) {
        case 'POST':
            if (ownedVenueId(db(), $adminId) !== null) {
                send_error('You already own a venue', 409);
                return;
            }
            send_json(saveVenueBundle(db(), null, read_body(), $adminId), 201);
            return;

        case 'PUT':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                send_error('id is required', 422);
                return;
            }
            require_venue_owner(db(), $id);
            send_json(saveVenueBundle(db(), $id, read_body()));
            return;

        default:
            send_error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 422);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
