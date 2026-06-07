<?php

use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM users');
    }

    public function test_register_creates_user_with_hashed_password(): void
    {
        $id = register($this->pdo, 'Farras', 'farras@example.com', 'secret123');
        $this->assertGreaterThan(0, $id);

        $row = $this->pdo->query('SELECT password_hash FROM users WHERE id = ' . $id)->fetch();
        $this->assertNotSame('secret123', $row['password_hash'], 'password must not be stored as plaintext');
        $this->assertTrue(password_verify('secret123', $row['password_hash']));
    }

    public function test_register_rejects_duplicate_email(): void
    {
        register($this->pdo, 'Farras', 'dup@example.com', 'secret123');

        $this->expectException(DuplicateEmailException::class);
        register($this->pdo, 'Someone', 'dup@example.com', 'other123');
    }

    public function test_register_rejects_empty_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        register($this->pdo, '', 'x@example.com', 'secret123');
    }

    public function test_register_rejects_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        register($this->pdo, 'Farras', 'not-an-email', 'secret123');
    }

    public function test_login_succeeds_with_correct_credentials(): void
    {
        register($this->pdo, 'Farras', 'login@example.com', 'secret123');

        $user = login($this->pdo, 'login@example.com', 'secret123');
        $this->assertNotNull($user);
        $this->assertSame('login@example.com', $user['email']);
        $this->assertArrayNotHasKey('password_hash', $user, 'login must not leak the hash');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        register($this->pdo, 'Farras', 'wrong@example.com', 'secret123');

        $this->assertNull(login($this->pdo, 'wrong@example.com', 'nope'));
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $this->assertNull(login($this->pdo, 'ghost@example.com', 'secret123'));
    }
}
