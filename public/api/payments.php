<?php
// POST /api/payments.php  body: { booking_id }  -> pay for the caller's
// pending booking (simulated). Requires a logged-in session.
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/http.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/payments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed', 405);
    return;
}

$user_id = require_login();
$body = read_body();
$booking_id = (int) ($body['booking_id'] ?? 0);

if ($booking_id <= 0) {
    send_error('booking_id is required', 422);
    return;
}

try {
    send_json(payForBooking(db(), $user_id, $booking_id), 201);
} catch (NotBookingOwnerException $e) {
    send_error($e->getMessage(), 403);
} catch (BookingNotPayableException $e) {
    send_error($e->getMessage(), 422);
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 404);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
