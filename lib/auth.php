<?php
// Authentication logic. Kept here (not in endpoints) so it is unit-testable.
// Passwords are stored only as a hash via password_hash().

class DuplicateEmailException extends RuntimeException {}

// Register a new user. Returns the new user id.
// Throws InvalidArgumentException on bad input, DuplicateEmailException if the
// email is already taken.
function register(PDO $pdo, string $name, string $email, string $password): int
{
    $name  = trim($name);
    $email = trim($email);

    if ($name === '' || $email === '' || $password === '') {
        throw new InvalidArgumentException('Name, email, and password are required');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email address');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // integrity constraint violation
            throw new DuplicateEmailException('Email is already registered');
        }
        throw $e;
    }

    return (int) $pdo->lastInsertId();
}

// Verify credentials. Returns the user (id, name, email, role) on success, or null.
function login(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ?');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    unset($user['password_hash']);
    $user['id'] = (int) $user['id'];
    return $user;
}

// True only if the user exists and has the admin role.
function is_admin(PDO $pdo, int $user_id): bool
{
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $role = $stmt->fetchColumn();
    return $role === 'admin';
}
