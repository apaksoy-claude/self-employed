<?php
declare(strict_types=1);

namespace AP\Modules\Users\Models;

use AP\Core\Model;

final class Role extends Model
{
    protected static string $table = 'roles';
    protected static array $casts = ['is_system' => 'bool'];
    protected static array $guarded = ['id'];

    /** @return string[] */
    public function permissions(): array
    {
        return self::db()->table('role_permissions')->where('role_id', (int) $this->id)->pluck('permission');
    }
}
