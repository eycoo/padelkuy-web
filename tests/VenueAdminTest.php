<?php

use PHPUnit\Framework\TestCase;

final class VenueAdminTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
    }

    public function test_create_persists_and_returns_id(): void
    {
        $id = createVenue($this->pdo, [
            'name' => 'New Club',
            'city' => 'Jakarta Selatan',
            'price_per_hour' => 180000,
            'tag' => 'Premium turf',
            'image_path' => 'assets/images/x.jpeg',
        ]);
        $this->assertGreaterThan(0, $id);

        $v = getVenue($this->pdo, $id);
        $this->assertSame('New Club', $v['name']);
        $this->assertSame('Jakarta Selatan', $v['city']);
        $this->assertSame(180000, $v['price_per_hour']);
        $this->assertSame('Premium turf', $v['tag']);
    }

    public function test_get_returns_null_for_missing(): void
    {
        $this->assertNull(getVenue($this->pdo, 999999));
    }

    public function test_update_changes_fields(): void
    {
        $id = createVenue($this->pdo, ['name' => 'Old', 'city' => 'Bandung', 'price_per_hour' => 100000]);

        updateVenue($this->pdo, $id, [
            'name' => 'Renamed',
            'city' => 'Surabaya',
            'price_per_hour' => 250000,
            'tag' => 'AC',
            'image_path' => null,
        ]);

        $v = getVenue($this->pdo, $id);
        $this->assertSame('Renamed', $v['name']);
        $this->assertSame('Surabaya', $v['city']);
        $this->assertSame(250000, $v['price_per_hour']);
    }

    public function test_delete_removes_venue_and_cascades_courts(): void
    {
        $id = createVenue($this->pdo, ['name' => 'Doomed', 'city' => 'Jakarta', 'price_per_hour' => 120000]);
        $this->pdo->exec("INSERT INTO courts (venue_id, label) VALUES ($id, 'A')");

        $this->assertTrue(deleteVenue($this->pdo, $id));
        $this->assertNull(getVenue($this->pdo, $id));

        $courts = $this->pdo->query("SELECT COUNT(*) FROM courts WHERE venue_id = $id")->fetchColumn();
        $this->assertSame(0, (int) $courts, 'courts must cascade with the venue');
    }

    public function test_delete_returns_false_for_missing(): void
    {
        $this->assertFalse(deleteVenue($this->pdo, 999999));
    }

    public function test_rejects_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createVenue($this->pdo, ['name' => '', 'city' => 'Jakarta', 'price_per_hour' => 100000]);
    }

    public function test_rejects_empty_city(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createVenue($this->pdo, ['name' => 'X', 'city' => '', 'price_per_hour' => 100000]);
    }

    public function test_rejects_non_positive_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createVenue($this->pdo, ['name' => 'X', 'city' => 'Jakarta', 'price_per_hour' => 0]);
    }
}
