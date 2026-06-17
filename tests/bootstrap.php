<?php
// Test bootstrap: point the app at a throwaway test database and ensure the
// schema exists. Each test class is responsible for cleaning its own rows.

putenv('DB_NAME=padelkuy_test');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/venues.php';
require_once __DIR__ . '/../lib/courts.php';
require_once __DIR__ . '/../lib/availability.php';
require_once __DIR__ . '/../lib/bookings.php';
require_once __DIR__ . '/../lib/payments.php';
require_once __DIR__ . '/../lib/receipt.php';

(function () {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    // Connect without a database to create the test one.
    $root = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $root->exec('CREATE DATABASE IF NOT EXISTS padelkuy_test
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    // Build the schema in the test database from schema.sql, retargeted.
    $sql = file_get_contents(__DIR__ . '/../schema.sql');
    $sql = preg_replace('/`?padelkuy`?/', 'padelkuy_test', $sql);
    $root->exec($sql);
})();
