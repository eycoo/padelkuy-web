<?php
// Small helpers for JSON API responses.

function send_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function send_error(string $message, int $status = 400): void
{
    send_json(['error' => $message], $status);
}

// Read the request body as an associative array. Accepts a JSON body or a
// form-encoded POST.
function read_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}
