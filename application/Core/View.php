<?php
declare(strict_types=1);

namespace AP\Core;

use Closure;
use RuntimeException;

/** Plain-PHP template engine: layouts, sections, partials, components and slots. */
final class View
{
    /** @var array<string,string> namespace => directory */
    private static array $namespaces = [];
    /** @var array<string, array<int, array<int, mixed>>> slot => priority => items */
    private static array $slots = [];

    private array $sections = [];
    private ?string $layout = null;
    private array $layoutData = [];
    private array $openSections = [];

    public static function addNamespace(string $namespace, string $directory): void
    {
        self::$namespaces[$namespace] = rtrim($directory, '/');
    }

    /** Let a module add content to a named place (e.g. "head", "body_end", "admin.dashboard"). */
    public static function inject(string $slot, string|callable $content, int $priority = 10): void
    {
        self::$slots[$slot][$priority][] = $content;
    }

    public static function resetSlots(): void
    {
        self::$slots = [];
    }

    public static function render(string $name, array $data = []): string
    {
        return (new self())->make($name, $data);
    }

    public static function exists(string $name): bool
    {
        return self::resolve($name) !== null;
    }

    private static function resolve(string $name): ?string
    {
        $ns = 'app';
        if (str_contains($name, '::')) {
            [$ns, $name] = explode('::', $name, 2);
        }
        $dir = self::$namespaces[$ns] ?? null;
        if ($dir === null) {
            return null;
        }
        $file = $dir . '/' . str_replace('.', '/', $name) . '.php';
        return is_file($file) ? $file : null;
    }

    public function make(string $name, array $data = []): string
    {
        $file = self::resolve($name);
        if ($file === null) {
            throw new RuntimeException("View not found: {$name}");
        }
        $content = $this->evaluate($file, $data);
        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['content'] ??= $content;
            return $this->make($layout, $data + $this->layoutData);
        }
        return $content;
    }

    private function evaluate(string $__file, array $__data): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            $fn = function () use ($__file, $__data): void {
                extract($__data, EXTR_SKIP);
                include $__file;
            };
            Closure::bind($fn, $this, self::class)();
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /* ---- template helpers (used inside views as $this->...) ---- */

    public function extend(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function section(string $name): void
    {
        $this->openSections[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->openSections);
        if ($name === null) {
            throw new RuntimeException('endSection() without section().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    /** Print a section's content (or a default). */
    public function show(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    public function include(string $name, array $data = []): void
    {
        echo (new self())->make($name, $data);
    }

    public function component(string $name, array $data = []): void
    {
        $this->include(str_contains($name, '::') ? $name : 'components.' . $name, $data);
    }

    /** Print everything modules injected into a slot. */
    /** Whether anything was added to a slot (lets a module replace a whole part of a layout). */
    public function hasSlot(string $name): bool
    {
        return !empty(self::$slots[$name]);
    }

    /** Everything injected into a slot, as a string (for modules that build a part of the layout themselves). */
    public static function slotHtml(string $name): string
    {
        $items = self::$slots[$name] ?? [];
        ksort($items);
        $out = '';
        foreach ($items as $list) {
            foreach ($list as $item) {
                $out .= is_callable($item) ? (string) $item() : $item;
            }
        }
        return $out;
    }

    public function slot(string $name): void
    {
        $items = self::$slots[$name] ?? [];
        ksort($items);
        foreach ($items as $list) {
            foreach ($list as $item) {
                echo is_callable($item) ? (string) $item() : $item;
            }
        }
    }
}
