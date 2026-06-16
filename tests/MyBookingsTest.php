<?php

use PHPUnit\Framework\TestCase;

final class MyBookingsTest extends TestCase
{
    private PDO $pdo;
    private string $date;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->date = date('Y-m-d', strtotime('+7 days'));
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec('DELETE FROM users');

        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (1, 'Test Venue', 'Jakarta', 100000)");
        $this->pdo->exec("INSERT INTO courts (id, venue_id, label) VALUES (1, 1, 'A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Alice', 'a@e.com', 'x'), (2, 'Bob', 'b@e.com', 'x')");
    }

    public function test_returns_only_the_users_own_bookings(): void
    {
        createBooking($this->pdo, 1, 1, $this->date, 8, 9);   // Alice
        createBooking($this->pdo, 2, 1, $this->date, 10, 11); // Bob

        $mine = listUserBookings($this->pdo, 1);
        $this->assertCount(1, $mine);
        $this->assertSame(8, $mine[0]['start_hour']);
        $this->assertSame(100000, $mine[0]['price']);
    }

    public function test_empty_when_user_has_no_bookings(): void
    {
        $this->assertSame([], listUserBookings($this->pdo, 1));
    }

    public function test_includes_venue_and_court_labels(): void
    {
        createBooking($this->pdo, 1, 1, $this->date, 8, 9);
        $mine = listUserBookings($this->pdo, 1);
        $this->assertSame('Test Venue', $mine[0]['venue_name']);
        $this->assertSame('A', $mine[0]['court_label']);
    }
}
