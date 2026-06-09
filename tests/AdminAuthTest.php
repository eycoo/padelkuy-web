<?php

use PHPUnit\Framework\TestCase;

final class AdminAuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM users');
    }

    public function test_is_admin_true_for_admin_account(): void
    {
        $id = register($this->pdo, 'Boss', 'boss@example.com', 'secret123');
        $this->pdo->exec("UPDATE users SET role = 'admin' WHERE id = $id");

        $this->assertTrue(is_admin($this->pdo, $id));
    }

    public function test_is_admin_false_for_normal_user(): void
    {
        $id = register($this->pdo, 'Customer', 'cust@example.com', 'secret123');

        $this->assertFalse(is_admin($this->pdo, $id));
    }

    public function test_is_admin_false_for_unknown_id(): void
    {
        $this->assertFalse(is_admin($this->pdo, 999999));
    }

    public function test_new_user_defaults_to_user_role(): void
    {
        $id = register($this->pdo, 'Default', 'def@example.com', 'secret123');

        $row = $this->pdo->query("SELECT role FROM users WHERE id = $id")->fetch();
        $this->assertSame('user', $row['role']);
    }

    public function test_login_includes_role(): void
    {
        $id = register($this->pdo, 'Boss', 'role@example.com', 'secret123');
        $this->pdo->exec("UPDATE users SET role = 'admin' WHERE id = $id");

        $user = login($this->pdo, 'role@example.com', 'secret123');
        $this->assertSame('admin', $user['role']);
        $this->assertArrayNotHasKey('password_hash', $user, 'login must not leak the hash');
    }
}
