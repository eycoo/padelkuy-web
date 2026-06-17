<?php

use PHPUnit\Framework\TestCase;

// Covers the admin venue editor extras: About text (#43), facility chips (#44),
// and the main image + detail gallery (#45).
final class VenueDetailsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM venue_images');
        $this->pdo->exec('DELETE FROM venue_facilities');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
    }

    private function makeVenue(array $extra = []): int
    {
        return createVenue($this->pdo, array_merge(
            ['name' => 'V', 'city' => 'Jakarta', 'price_per_hour' => 100000],
            $extra
        ));
    }

    // --- #43 description ---

    public function test_create_and_get_persist_description(): void
    {
        $id = $this->makeVenue(['description' => 'Cozy club with glass walls.']);
        $this->assertSame('Cozy club with glass walls.', getVenue($this->pdo, $id)['description']);
    }

    public function test_description_null_when_empty(): void
    {
        $id = $this->makeVenue(['description' => '']);
        $this->assertNull(getVenue($this->pdo, $id)['description']);
    }

    public function test_update_changes_description(): void
    {
        $id = $this->makeVenue(['description' => 'old']);
        updateVenue($this->pdo, $id, ['name' => 'V', 'city' => 'Jakarta', 'price_per_hour' => 100000, 'description' => 'new']);
        $this->assertSame('new', getVenue($this->pdo, $id)['description']);
    }

    // --- #45 main image + gallery ---

    public function test_main_image_path_round_trips(): void
    {
        $id = $this->makeVenue(['main_image_path' => 'assets/images/hero.jpg']);
        $this->assertSame('assets/images/hero.jpg', getVenue($this->pdo, $id)['main_image_path']);
    }

    public function test_set_images_are_returned_in_order(): void
    {
        $id = $this->makeVenue();
        setImages($this->pdo, $id, ['assets/images/a.jpg', 'assets/images/b.jpg']);
        $this->assertSame(['assets/images/a.jpg', 'assets/images/b.jpg'], listImages($this->pdo, $id));
        $this->assertSame(['assets/images/a.jpg', 'assets/images/b.jpg'], getVenue($this->pdo, $id)['images']);
    }

    public function test_set_images_replaces_and_drops_blanks(): void
    {
        $id = $this->makeVenue();
        setImages($this->pdo, $id, ['assets/images/a.jpg', 'assets/images/b.jpg']);
        setImages($this->pdo, $id, ['assets/images/c.jpg', '', '  ']);
        $this->assertSame(['assets/images/c.jpg'], listImages($this->pdo, $id));
    }

    public function test_images_cascade_on_venue_delete(): void
    {
        $id = $this->makeVenue();
        setImages($this->pdo, $id, ['assets/images/a.jpg']);
        deleteVenue($this->pdo, $id);
        $n = $this->pdo->query("SELECT COUNT(*) FROM venue_images WHERE venue_id = $id")->fetchColumn();
        $this->assertSame(0, (int) $n);
    }

    // --- #44 facilities ---

    public function test_set_facilities_are_returned(): void
    {
        $id = $this->makeVenue();
        setFacilities($this->pdo, $id, ['Shower', 'Parking']);
        $this->assertSame(['Shower', 'Parking'], listFacilities($this->pdo, $id));
        $this->assertSame(['Shower', 'Parking'], getVenue($this->pdo, $id)['facilities']);
    }

    public function test_set_facilities_replaces_dedupes_and_drops_blanks(): void
    {
        $id = $this->makeVenue();
        setFacilities($this->pdo, $id, ['Shower', 'Parking']);
        setFacilities($this->pdo, $id, ['Cafe', 'Cafe', ' Shower ', '']);
        $this->assertSame(['Cafe', 'Shower'], listFacilities($this->pdo, $id));
    }

    public function test_facilities_cascade_on_venue_delete(): void
    {
        $id = $this->makeVenue();
        setFacilities($this->pdo, $id, ['Shower']);
        deleteVenue($this->pdo, $id);
        $n = $this->pdo->query("SELECT COUNT(*) FROM venue_facilities WHERE venue_id = $id")->fetchColumn();
        $this->assertSame(0, (int) $n);
    }
}
