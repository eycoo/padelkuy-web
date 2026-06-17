<?php
// GET /api/receipt.php?booking_id=N -> stream the PDF payment receipt (kuitansi)
// for the caller's paid booking (ADR-0004). Requires a logged-in session.
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/http.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/receipt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed', 405);
    return;
}

$user_id = require_login();
$booking_id = (int) ($_GET['booking_id'] ?? 0);

if ($booking_id <= 0) {
    send_error('booking_id is required', 422);
    return;
}

try {
    $pdf = generateReceiptForUser(db(), $user_id, $booking_id);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="kuitansi-' . $booking_id . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
} catch (NotBookingOwnerException $e) {
    send_error($e->getMessage(), 403);
} catch (ReceiptNotAvailableException $e) {
    send_error($e->getMessage(), 422);
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 404);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
