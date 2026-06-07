<?php
// POST /api/login.php  body: { email, password }
// On success starts a session and returns the user.
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/http.php';
require_once __DIR__ . '/../../lib/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed', 405);
    return;
}

$body = read_body();

try {
    $user = login(
        db(),
        (string) ($body['email'] ?? ''),
        (string) ($body['password'] ?? '')
    );

    if ($user === null) {
        send_error('Invalid email or password', 401);
        return;
    }

    session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    send_json($user);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
