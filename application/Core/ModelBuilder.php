<?php
declare(strict_types=1);

namespace AP\Core;

/** Query builder wrapper that returns model instances. */
final class ModelBuilder
{
    /** @param class-string<Model> $class */
    public function __construct(private string $class, private QueryBuilder $query, bool $softDeletes)
    {
        if ($softDeletes) {
            $this->query->whereNull('deleted_at');
        }
    }

    public function where(string|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        func_num_args() === 2 ? $this->query->where($column, $operator) : $this->query->where($column, $operator, $value);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function search(array $columns, string $term): self
    {
        $this->query->search($columns, $term);
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    public function limit(int $n): self
    {
        $this->query->limit($n);
        return $this;
    }

    public function get(): array
    {
        return array_map(fn(array $row) => ($this->class)::hydrate($row), $this->query->get());
    }

    public function first(): ?Model
    {
        $row = $this->query->first();
        return $row ? ($this->class)::hydrate($row) : null;
    }

    public function count(): int
    {
        return $this->query->count();
    }

    /** @return array{data: Model[], total: int, page: int, per_page: int, last_page: int} */
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $result = $this->query->paginate($page, $perPage);
        $result['data'] = array_map(fn(array $row) => ($this->class)::hydrate($row), $result['data']);
        return $result;
    }

    public function builder(): QueryBuilder
    {
        return $this->query;
    }
}
