<?php
declare(strict_types=1);

namespace AP\Core;

use RuntimeException;

/** Tiny service container / registry. */
final class App
{
    private static ?App $instance = null;
    /** @var array<string, array{0: callable, 1: bool}> */
    private array $bindings = [];
    private array $instances = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function bind(string $id, callable $factory, bool $shared = true): void
    {
        $this->bindings[$id] = [$factory, $shared];
        unset($this->instances[$id]);
    }

    public function set(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    /** Used by the test runner to put the container back exactly as it was. */
    public function snapshot(): array
    {
        return ['bindings' => $this->bindings, 'instances' => $this->instances];
    }

    public function restore(array $snapshot): void
    {
        $this->bindings = $snapshot['bindings'];
        $this->instances = $snapshot['instances'];
    }

    public function make(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {
            [$factory, $shared] = $this->bindings[$id];
            $object = $factory($this);
            if ($shared) {
                $this->instances[$id] = $object;
            }
            return $object;
        }
        if (class_exists($id)) {
            return new $id();
        }
        throw new RuntimeException("Service not found: {$id}");
    }
}
