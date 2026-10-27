<?php
declare(strict_types=1);

namespace AP\Core;

/** Simple Active Record base class. */
abstract class Model implements \ArrayAccess, \JsonSerializable
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $casts = [];
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;
    /** @var string[] attributes that mass assignment must ignore */
    protected static array $guarded = [];

    protected array $attributes = [];
    protected array $original = [];
    public bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function db(): DB
    {
        /** @var DB $db */
        $db = App::instance()->make(DB::class);
        return $db;
    }

    public static function tableName(): string
    {
        return static::$table;
    }

    public static function query(): ModelBuilder
    {
        return new ModelBuilder(static::class, static::db()->table(static::$table), static::$softDeletes);
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    public static function findOrFail(int|string $id): static
    {
        return static::find($id) ?? throw new HttpException(404);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function where(string|\Closure $column, mixed $operator = null, mixed $value = null): ModelBuilder
    {
        $q = static::query();
        return func_num_args() === 2 ? $q->where($column, $operator) : $q->where($column, $operator, $value);
    }

    public static function create(array $attributes): static
    {
        $m = new static($attributes);
        $m->save();
        return $m;
    }

    public static function hydrate(array $row): static
    {
        $m = new static();
        $m->attributes = $row;
        $m->original = $row;
        $m->exists = true;
        return $m;
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $k => $v) {
            if (!in_array($k, static::$guarded, true)) {
                $this->__set((string) $k, $v);
            }
        }
        return $this;
    }

    public function __get(string $name): mixed
    {
        if (!array_key_exists($name, $this->attributes)) {
            return null;
        }
        return $this->cast($name, $this->attributes[$name]);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    private function cast(string $name, mixed $value): mixed
    {
        if ($value === null || !isset(static::$casts[$name])) {
            return $value;
        }
        return match (static::$casts[$name]) {
            'int', 'integer' => (int) $value,
            'float' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'json', 'array' => is_string($value) ? (json_decode($value, true) ?? []) : $value,
            default => $value,
        };
    }

    private function prepareForStorage(): array
    {
        $out = [];
        foreach ($this->attributes as $k => $v) {
            $cast = static::$casts[$k] ?? null;
            if (($cast === 'json' || $cast === 'array') && !is_string($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif ($cast === 'bool' || $cast === 'boolean') {
                $v = $v ? 1 : 0;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    public function save(): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $pk = static::$primaryKey;
        if ($this->exists) {
            if (static::$timestamps) {
                $this->attributes['updated_at'] = $now;
            }
            $data = $this->prepareForStorage();
            unset($data[$pk]);
            $dirty = array_filter($data, fn($v, $k) => !array_key_exists($k, $this->original) || $this->original[$k] != $v, ARRAY_FILTER_USE_BOTH);
            if ($dirty) {
                static::db()->table(static::$table)->where($pk, $this->attributes[$pk])->update($dirty);
            }
        } else {
            if (static::$timestamps) {
                $this->attributes['created_at'] ??= $now;
                $this->attributes['updated_at'] ??= $now;
            }
            $id = static::db()->table(static::$table)->insertGetId($this->prepareForStorage());
            if ($id > 0 && !isset($this->attributes[$pk])) {
                $this->attributes[$pk] = $id;
            }
            $this->exists = true;
        }
        $this->original = $this->attributes;
        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        $q = static::db()->table(static::$table)->where(static::$primaryKey, $this->attributes[static::$primaryKey]);
        if (static::$softDeletes) {
            $q->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        } else {
            $q->delete();
        }
        $this->exists = false;
        return true;
    }

    public function restore(): bool
    {
        if (!static::$softDeletes) {
            return false;
        }
        static::db()->table(static::$table)->where(static::$primaryKey, $this->attributes[static::$primaryKey])->update(['deleted_at' => null]);
        $this->attributes['deleted_at'] = null;
        $this->exists = true;
        return true;
    }

    public function hasMany(string $class, string $foreignKey): ModelBuilder
    {
        return $class::where($foreignKey, $this->attributes[static::$primaryKey] ?? 0);
    }

    public function belongsTo(string $class, string $foreignKey): ?Model
    {
        $id = $this->attributes[$foreignKey] ?? null;
        return $id === null ? null : $class::find($id);
    }

    public function toArray(): array
    {
        $out = [];
        foreach ($this->attributes as $k => $v) {
            $out[$k] = $this->cast($k, $v);
        }
        return $out;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
