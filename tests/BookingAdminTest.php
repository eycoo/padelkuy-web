<?php

use PHPUnit\Framework\TestCase;

final class BookingAdminTest extends TestCase
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

        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES
            (1, 'Venue One', 'Jakarta', 100000),
            (2, 'Venue Two', 'Bandung', 200000)");
        $this->pdo->exec("INSERT INTO courts (id, venue_id, label) VALUES (1, 1, 'A'), (2, 2, 'A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES
            (1, 'Alice', 'a@e.com', 'x'),
            (2, 'Bob',   'b@e.com', 'x')");
    }

    public function test_lists_bookings_across_all_users(): void
    {
        createBooking($this->pdo, 1, 1, $this->date, 8, 9);   // Alice @ venue 1
        createBooking($this->pdo, 2, 2, $this->date, 10, 11); // Bob   @ venue 2

        $all = listAllBookings($this->pdo);
        $this->assertCount(2, $all);
    }

    public function test_includes_booker_name(): void
    {
        createBooking($this->pdo, 1, 1, $this->date, 8, 9);

        $all = listAllBookings($this->pdo);
        $this->assertSame('Alice', $all[0]['user_name']);
        $this->assertSame('Venue One', $all[0]['venue_name']);
    }

    public function test_filters_by_venue(): void
    {
        createBooking($this->pdo, 1, 1, $this->date, 8, 9);   // venue 1
        createBooking($this->pdo, 2, 2, $this->date, 10, 11); // venue 2

        $only = listAllBookings($this->pdo, ['venue_id' => 2]);
        $this->assertCount(1, $only);
        $this->assertSame('Venue Two', $only[0]['venue_name']);
    }

    public function test_filters_by_date(): void
    {
        $d1 = date('Y-m-d', strtotime('+7 days'));
        $d2 = date('Y-m-d', strtotime('+8 days'));
        createBooking($this->pdo, 1, 1, $d1, 8, 9);
        createBooking($this->pdo, 1, 1, $d2, 8, 9);

        $only = listAllBookings($this->pdo, ['date' => $d2]);
        $this->assertCount(1, $only);
        $this->assertSame($d2, $only[0]['date']);
    }

    public function test_cancel_is_soft_and_marks_the_booking_cancelled(): void
    {
        $id = createBooking($this->pdo, 1, 1, $this->date, 8, 10);

        $this->assertTrue(cancelBooking($this->pdo, $id));

        $b = getBooking($this->pdo, $id);
        $this->assertNotNull($b, 'cancel is soft: the booking row persists');
        $this->assertSame('cancelled', $b['status']);
    }

    public function test_cancel_returns_false_for_missing(): void
    {
        $this->assertFalse(cancelBooking($this->pdo, 999999));
    }

    public function test_admin_cancel_of_a_paid_booking_refunds_it_regardless_of_age(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 8, 10);
        payForBooking($this->pdo, 1, $bid);
        // Older than the customer 5-minute window — admin has no window.
        $this->pdo->exec("UPDATE payments SET paid_at = NOW() - INTERVAL 60 MINUTE WHERE booking_id = $bid");

        $this->assertTrue(adminCancelBooking($this->pdo, $bid));

        $row = array_values(array_filter(listAllBookings($this->pdo), fn($r) => $r['id'] === $bid))[0];
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('refunded', $row['payment_status']);
    }

    public function test_admin_cancel_of_an_unpaid_booking_is_a_plain_soft_cancel(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 8, 10);

        $this->assertTrue(adminCancelBooking($this->pdo, $bid));
        $this->assertSame('cancelled', getBooking($this->pdo, $bid)['status']);
    }

    public function test_list_includes_code_status_and_payment_status(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 8, 9);
        payForBooking($this->pdo, 1, $bid);

        $all = listAllBookings($this->pdo);
        $this->assertMatchesRegularExpression('/^PDL-\d{4,}$/', $all[0]['code']);
        $this->assertSame('paid', $all[0]['status']);
        $this->assertSame('paid', $all[0]['payment_status']);
    }

    // --- #49 status filter + pagination ---

    public function test_filters_by_status(): void
    {
        $paid = createBooking($this->pdo, 1, 1, $this->date, 8, 9);
        payForBooking($this->pdo, 1, $paid);
        createBooking($this->pdo, 2, 2, $this->date, 10, 11); // stays pending

        $onlyPaid = listAllBookings($this->pdo, ['status' => 'paid']);
        $this->assertCount(1, $onlyPaid);
        $this->assertSame('paid', $onlyPaid[0]['status']);

        $onlyPending = listAllBookings($this->pdo, ['status' => 'pending']);
        $this->assertCount(1, $onlyPending);
        $this->assertSame('pending', $onlyPending[0]['status']);
    }

    public function test_limit_and_offset_window(): void
    {
        for ($h = 8; $h < 13; $h++) {
            createBooking($this->pdo, 1, 1, $this->date, $h, $h + 1); // 5 bookings
        }

        $page1 = listAllBookings($this->pdo, ['limit' => 2, 'offset' => 0]);
        $page2 = listAllBookings($this->pdo, ['limit' => 2, 'offset' => 2]);
        $page3 = listAllBookings($this->pdo, ['limit' => 2, 'offset' => 4]);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertCount(1, $page3);
        // windows must not overlap
        $ids = array_merge(array_column($page1, 'id'), array_column($page2, 'id'), array_column($page3, 'id'));
        $this->assertCount(5, array_unique($ids));
    }

    public function test_count_total_ignores_paging_but_honours_filters(): void
    {
        for ($h = 8; $h < 13; $h++) {
            createBooking($this->pdo, 1, 1, $this->date, $h, $h + 1);
        }
        createBooking($this->pdo, 2, 2, $this->date, 8, 9); // venue 2

        $this->assertSame(6, countAllBookings($this->pdo));
        $this->assertSame(1, countAllBookings($this->pdo, ['venue_id' => 2]));
        $this->assertSame(5, countAllBookings($this->pdo, ['venue_id' => 1, 'limit' => 2]));
    }
}
