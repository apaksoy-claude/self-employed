<?php
declare(strict_types=1);

namespace AP\Tests;

/** Stand-in for the Auth service so views can be rendered without a database. */
final class FakeAuth
{
    public function user(): ?array
    {
        return ['id' => '1', 'name' => 'Test Admin', 'email' => 'admin@example.com', 'is_super' => '1', 'role_id' => '1', 'status' => 'active', 'must_change_password' => '0'];
    }

    public function id(): ?int
    {
        return 1;
    }

    public function can(string $permission): bool
    {
        return true;
    }

    public function mustChangePassword(): bool
    {
        return false;
    }

    public function currentTokenHash(): string
    {
        return 'current-hash';
    }
}
