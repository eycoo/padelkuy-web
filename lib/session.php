<?php
// Session helpers for reading the logged-in user.

// Return the logged-in user's id, or null if no active session.
function current_user_id(): ?int
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

// Send a 401 and stop if no user is logged in; otherwise return the user id.
function require_login(): int
{
    $id = current_user_id();
    if ($id === null) {
        send_error('Authentication required', 401);
        exit;
    }
    return $id;
}
