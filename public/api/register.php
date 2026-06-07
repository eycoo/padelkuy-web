<?php
// POST /api/register.php  body: { name, email, password }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/http.php';
require_once __DIR__ . '/../../lib/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed', 405);
    return;
}

$body = read_body();

try {
    $id = register(
        db(),
        (string) ($body['name'] ?? ''),
        (string) ($body['email'] ?? ''),
        (string) ($body['password'] ?? '')
    );
    send_json(['id' => $id], 201);
} catch (InvalidArgumentException $e) {
    send_error($e->getMessage(), 422);
} catch (DuplicateEmailException $e) {
    send_error($e->getMessage(), 409);
} catch (Throwable $e) {
    send_error('Internal server error', 500);
}
