<?php
declare(strict_types=1);

namespace AP\Tests;

use AP\Core\Model;

/** A model used only by the test runner. */
final class TestUserModel extends Model
{
    protected static string $table = 'users';
    protected static bool $softDeletes = true;
    protected static array $casts = ['is_super' => 'bool'];
}
