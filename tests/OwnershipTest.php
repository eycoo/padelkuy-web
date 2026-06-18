<?php

use PHPUnit\Framework\TestCase;

// ADR-0006: an admin owns exactly one venue and only sees/touches that venue's
// data. These cover the ownership predicates the endpoint guards are built on.
final class OwnershipTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec('DELETE FROM users');
    }

    private function admin(string $email): int
    {
        return registerAdmin($this->pdo, 'Owner', $email, 'secret123');
    }

    private function venueFor(int $owner): int
    {
        return createVenue($this->pdo, ['name' => 'V', 'city' => 'Jakarta', 'price_per_hour' => 100000], $owner);
    }

    public function test_registerAdmin_creates_an_admin(): void
    {
        $id = $this->admin('a@example.com');
        $this->assertTrue(is_admin($this->pdo, $id));
    }

    public function test_registerAdmin_rejects_duplicate_email(): void
    {
        $this->admin('dup@example.com');
        $this->expectException(DuplicateEmailException::class);
        $this->admin('dup@example.com');
    }

    public function test_createVenue_sets_owner(): void
    {
        $owner = $this->admin('a@example.com');
        $vid = $this->venueFor($owner);

        $this->assertSame($vid, ownedVenueId($this->pdo, $owner));
        $this->assertTrue(ownsVenue($this->pdo, $owner, $vid));
    }

    public function test_ownedVenueId_is_null_without_a_venue(): void
    {
        $owner = $this->admin('a@example.com');
        $this->assertNull(ownedVenueId($this->pdo, $owner));
    }

    public function test_admin_does_not_own_anothers_venue(): void
    {
        $a = $this->admin('a@example.com');
        $b = $this->admin('b@example.com');
        $vid = $this->venueFor($a);

        $this->assertFalse(ownsVenue($this->pdo, $b, $vid));
        $this->assertNull(ownedVenueId($this->pdo, $b));
    }

    public function test_ownsCourt_follows_the_venue_owner(): void
    {
        $a = $this->admin('a@example.com');
        $b = $this->admin('b@example.com');
        $cid = createCourt($this->pdo, $this->venueFor($a), 'A');

        $this->assertTrue(ownsCourt($this->pdo, $a, $cid));
        $this->assertFalse(ownsCourt($this->pdo, $b, $cid));
    }

    public function test_ownsBooking_follows_the_venue_owner(): void
    {
        $a = $this->admin('a@example.com');
        $b = $this->admin('b@example.com');
        $cust = register($this->pdo, 'Cust', 'c@example.com', 'secret123');
        $cid = createCourt($this->pdo, $this->venueFor($a), 'A');
        $bid = createBooking($this->pdo, $cust, $cid, date('Y-m-d', strtotime('+1 day')), 8, 9);

        $this->assertTrue(ownsBooking($this->pdo, $a, $bid));
        $this->assertFalse(ownsBooking($this->pdo, $b, $bid));
    }
}
