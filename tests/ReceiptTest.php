<?php

use PHPUnit\Framework\TestCase;

final class ReceiptTest extends TestCase
{
    private PDO $pdo;
    private string $date;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->date = date('Y-m-d', strtotime('+7 days'));
        $this->pdo->exec('DELETE FROM payments');
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec('DELETE FROM users');

        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (1, 'Test Venue', 'Jakarta', 100000)");
        $this->pdo->exec("INSERT INTO courts (id, venue_id, label) VALUES (1, 1, 'A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Alice', 'a@e.com', 'x'), (2, 'Bob', 'b@e.com', 'x')");
    }

    public function test_receipt_for_a_paid_booking_is_a_pdf(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20);
        payForBooking($this->pdo, 1, $bid);

        $pdf = generateReceiptForUser($this->pdo, 1, $bid);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertGreaterThan(0, strlen($pdf));
    }

    public function test_receipt_embeds_the_booking_code_and_amount(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20); // 2h * 100000
        payForBooking($this->pdo, 1, $bid);

        $pdf = generateReceiptForUser($this->pdo, 1, $bid);

        $code = getBooking($this->pdo, $bid)['code'];
        $this->assertStringContainsString($code, $pdf);
        $this->assertStringContainsString('200.000', $pdf); // Rp 200.000, two hours
    }

    public function test_only_the_owner_can_get_the_receipt(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20); // Alice's
        payForBooking($this->pdo, 1, $bid);

        $this->expectException(NotBookingOwnerException::class);
        generateReceiptForUser($this->pdo, 2, $bid); // Bob asks
    }

    public function test_an_unpaid_booking_has_no_receipt(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20); // still pending

        $this->expectException(ReceiptNotAvailableException::class);
        generateReceiptForUser($this->pdo, 1, $bid);
    }

    public function test_a_missing_booking_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        generateReceiptForUser($this->pdo, 1, 999999);
    }
}
