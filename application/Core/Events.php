<?php
declare(strict_types=1);

namespace AP\Core;

/** Events (fire and forget) and filters (modify a value). */
final class Events
{
    private array $listeners = [];
    private array $filters = [];

    public function listen(string $event, callable $callback, int $priority = 10): void
    {
        $this->listeners[$event][$priority][] = $callback;
    }

    public function dispatch(string $event, mixed ...$payload): void
    {
        $list = $this->listeners[$event] ?? [];
        ksort($list);
        foreach ($list as $callbacks) {
            foreach ($callbacks as $cb) {
                $cb(...$payload);
            }
        }
    }

    public function addFilter(string $name, callable $callback, int $priority = 10): void
    {
        $this->filters[$name][$priority][] = $callback;
    }

    public function filter(string $name, mixed $value, mixed ...$args): mixed
    {
        $list = $this->filters[$name] ?? [];
        ksort($list);
        foreach ($list as $callbacks) {
            foreach ($callbacks as $cb) {
                $value = $cb($value, ...$args);
            }
        }
        return $value;
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }
}
