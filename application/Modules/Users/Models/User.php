<?php
declare(strict_types=1);

namespace AP\Modules\Users\Models;

use AP\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';
    protected static bool $softDeletes = true;
    protected static array $casts = ['is_super' => 'bool', 'must_change_password' => 'bool', 'two_factor_enabled' => 'bool', 'failed_logins' => 'int'];
    protected static array $guarded = ['id'];
}
