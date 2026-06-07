<?php
// POST /api/logout.php  ends the current session.
require_once __DIR__ . '/../../lib/http.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed', 405);
    return;
}

session_start();
$_SESSION = [];
session_destroy();

send_json(['ok' => true]);
