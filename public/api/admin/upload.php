<?php
// Admin image upload (#51). Stores an image under public/assets/images/ and
// returns its web-relative path for use as image_path / main_image_path / a
// gallery entry.
//   POST /api/admin/upload.php   multipart field: "image"   -> { path }
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/http.php';
require_once __DIR__ . '/../../../lib/auth.php';
require_once __DIR__ . '/../../../lib/session.php';
require_once __DIR__ . '/../../../lib/uploads.php';

require_admin(db());

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed', 405);
    return;
}

if (!isset($_FILES['image'])) {
    send_error('No "image" file field in the request', 422);
    return;
}

try {
    $path = storeUploadedImage($_FILES['image'], __DIR__ . '/../../assets/images', 'assets/images');
    send_json(['path' => $path], 201);
} catch (UploadException $e) {
    send_error($e->getMessage(), 422);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
