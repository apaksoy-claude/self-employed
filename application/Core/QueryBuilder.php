<?php
declare(strict_types=1);

namespace AP\Core;

use InvalidArgumentException;

/** Fluent, injection-safe query builder (identifiers are validated, values are always bound). */
final class QueryBuilder
{
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $joins = [];
    private array $orders = [];
    private array $groups = [];
    private ?int $limit = null;
    private ?int $offset = null;

    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    public function __construct(private DB $db, private string $table)
    {
    }

    public function select(string ...$columns): self
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    public function where(string|\Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if ($column instanceof \Closure) {
            $sub = new self($this->db, $this->table);
            $column($sub);
            if ($sub->wheres) {
                $this->wheres[] = [$boolean, '(' . $sub->compileWheres(false) . ')'];
                array_push($this->bindings, ...$sub->bindings);
            }
            return $this;
        }
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $operator = strtoupper((string) $operator);
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Invalid operator: {$operator}");
        }
        if ($value === null) {
            $this->wheres[] = [$boolean, $this->q($column) . ($operator === '!=' || $operator === '<>' ? ' IS NOT NULL' : ' IS NULL')];
            return $this;
        }
        $this->wheres[] = [$boolean, $this->q($column) . " {$operator} ?"];
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->where($column, '=', $operator, 'OR');
        }
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        if (!$values) {
            $this->wheres[] = [$boolean, $not ? '1 = 1' : '1 = 0'];
            return $this;
        }
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [$boolean, $this->q($column) . ($not ? ' NOT IN' : ' IN') . " ({$marks})"];
        array_push($this->bindings, ...array_values($values));
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['AND', $this->q($column) . ' IS NULL'];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['AND', $this->q($column) . ' IS NOT NULL'];
        return $this;
    }

    /** Raw condition. Only pass trusted SQL here; values go in $bindings. */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [$boolean, '(' . $sql . ')'];
        array_push($this->bindings, ...$bindings);
        return $this;
    }

    /** Search several columns for the same text. */
    public function search(array $columns, string $term): self
    {
        $term = trim($term);
        if ($term === '' || !$columns) {
            return $this;
        }
        // "!" as the escape character works the same on MySQL, MariaDB and SQLite.
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
        $parts = [];
        foreach ($columns as $c) {
            $parts[] = $this->q($c) . " LIKE ? ESCAPE '!'";
        }
        return $this->whereRaw(implode(' OR ', $parts), array_fill(0, count($parts), $like));
    }

    public function join(string $table, string $a, string $op, string $b, string $type = 'INNER'): self
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true) || !in_array($op, ['=', '!=', '<', '>'], true)) {
            throw new InvalidArgumentException('Invalid join.');
        }
        $this->joins[] = "{$type} JOIN " . self::id($this->db->t($table)) . ' ON ' . $this->q($a) . " {$op} " . $this->q($b);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = $this->q($column) . (strtoupper($direction) === 'DESC' ? ' DESC' : ' ASC');
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $c) {
            $this->groups[] = $this->q($c);
        }
        return $this;
    }

    public function limit(int $n): self
    {
        $this->limit = max(0, $n);
        return $this;
    }

    public function offset(int $n): self
    {
        $this->offset = max(0, $n);
        return $this;
    }

    /** Validate and quote an identifier such as users.email or `users`.`email`. */
    public static function id(string $name): string
    {
        $name = str_replace('`', '', $name);
        if ($name === '*') {
            return '*';
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.([A-Za-z_][A-Za-z0-9_]*|\*))?$/', $name)) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }
        // "table.*" keeps the star unquoted: `table`.`*` would mean a column named "*".
        if (str_ends_with($name, '.*')) {
            return '`' . substr($name, 0, -2) . '`.*';
        }
        return '`' . str_replace('.', '`.`', $name) . '`';
    }

    /** Quote a column; "table.column" gets the table prefix applied automatically. */
    private function q(string $name): string
    {
        $name = str_replace('`', '', $name);
        if (str_contains($name, '.')) {
            [$t, $c] = explode('.', $name, 2);
            if ($this->db->prefix !== '' && !str_starts_with($t, $this->db->prefix)) {
                $t = $this->db->prefix . $t;
            }
            $name = $t . '.' . $c;
        }
        return self::id($name);
    }

    private function compileWheres(bool $withKeyword = true): string
    {
        if (!$this->wheres) {
            return '';
        }
        $sql = '';
        foreach ($this->wheres as $i => [$bool, $cond]) {
            $sql .= ($i === 0 ? '' : " {$bool} ") . $cond;
        }
        return ($withKeyword ? ' WHERE ' : '') . $sql;
    }

    public function toSql(): string
    {
        $cols = implode(', ', array_map(function (string $c): string {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_.]*(?:\.\*)?|\*)(?:\s+AS\s+([A-Za-z_][A-Za-z0-9_]*))?$/i', $c, $m)) {
                return $this->q($m[1]) . (isset($m[2]) ? ' AS `' . $m[2] . '`' : '');
            }
            return $c; // internal aggregate expressions such as COUNT(*) AS aggregate
        }, $this->columns));
        $sql = "SELECT {$cols} FROM " . self::id($this->table);
        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql .= $this->compileWheres();
        if ($this->groups) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }
        return $sql;
    }

    public function bindings(): array
    {
        return $this->bindings;
    }

    public function get(): array
    {
        return $this->db->select($this->toSql(), $this->bindings);
    }

    public function first(): ?array
    {
        $copy = clone $this;
        $copy->limit = 1;
        $rows = $copy->get();
        return $rows[0] ?? null;
    }

    public function find(int|string $id, string $key = 'id'): ?array
    {
        return (clone $this)->where($key, $id)->first();
    }

    public function value(string $column): mixed
    {
        $row = (clone $this)->select($column)->first();
        return $row ? reset($row) : null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $rows = $key ? (clone $this)->select($key, $column)->get() : (clone $this)->select($column)->get();
        $out = [];
        foreach ($rows as $row) {
            if ($key) {
                $out[$row[$key]] = $row[$column];
            } else {
                $out[] = $row[$column];
            }
        }
        return $out;
    }

    public function count(): int
    {
        $copy = clone $this;
        $copy->columns = ['COUNT(*) AS aggregate'];
        $copy->orders = [];
        $copy->limit = $copy->offset = null;
        $row = $copy->first();
        return (int) ($row['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $copy = clone $this;
        $copy->columns = ['SUM(' . $this->q($column) . ') AS aggregate'];
        $copy->orders = [];
        $copy->limit = $copy->offset = null;
        $row = $copy->first();
        return (float) ($row['aggregate'] ?? 0);
    }

    public function avg(string $column): float
    {
        $copy = clone $this;
        $copy->columns = ['AVG(' . $this->q($column) . ') AS aggregate'];
        $copy->orders = [];
        $copy->limit = $copy->offset = null;
        $row = $copy->first();
        return (float) ($row['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /** @return array{data: array, total: int, page: int, per_page: int, last_page: int} */
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min(500, $perPage));
        $total = $this->count();
        $last = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $last));
        $data = (clone $this)->limit($perPage)->offset(($page - 1) * $perPage)->get();
        return ['data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => $last];
    }

    public function insert(array $data): int
    {
        $cols = implode(', ', array_map([self::class, 'id'], array_keys($data)));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        return $this->db->execute('INSERT INTO ' . self::id($this->table) . " ({$cols}) VALUES ({$marks})", array_values($data));
    }

    public function insertGetId(array $data): int
    {
        $this->insert($data);
        return (int) $this->db->lastId();
    }

    public function update(array $data): int
    {
        $sets = implode(', ', array_map(static fn($c) => self::id((string) $c) . ' = ?', array_keys($data)));
        $sql = 'UPDATE ' . self::id($this->table) . " SET {$sets}" . $this->compileWheres();
        return $this->db->execute($sql, [...array_values($data), ...$this->bindings]);
    }

    public function delete(): int
    {
        return $this->db->execute('DELETE FROM ' . self::id($this->table) . $this->compileWheres(), $this->bindings);
    }

    public function increment(string $column, int $by = 1): int
    {
        $sql = 'UPDATE ' . self::id($this->table) . ' SET ' . $this->q($column) . ' = ' . $this->q($column) . ' + ?' . $this->compileWheres();
        return $this->db->execute($sql, [$by, ...$this->bindings]);
    }
}
