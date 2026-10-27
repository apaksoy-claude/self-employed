<?php
declare(strict_types=1);

namespace AP\Core;

/**
 * Translation system. Keys look like "namespace.key"; each namespace maps to a folder
 * holding one flat PHP array file per language (en.php, fr.php ...).
 */
final class Translator
{
    private string $locale = 'en';
    private string $fallback = 'en';
    /** @var array<string,string> */
    private array $namespaces = [];
    /** @var array<string, array<string, array<string,string>>> ns => locale => lines */
    private array $loaded = [];
    /** @var array<string,true> */
    private array $missing = [];
    /** @var (callable(string,string):array)|null returns edited translations for a namespace and language */
    private $overrides = null;

    public function addNamespace(string $namespace, string $directory): void
    {
        $this->namespaces[$namespace] = rtrim($directory, '/');
        unset($this->loaded[$namespace]);
    }

    public function namespaces(): array
    {
        return $this->namespaces;
    }

    /** Let a module supply translations edited in the admin (they win over the files). */
    public function setOverrides(?callable $loader): void
    {
        $this->overrides = $loader;
        $this->loaded = [];
    }

    public function setFallback(string $locale): void
    {
        $this->fallback = preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $locale) ? $locale : 'en';
        $this->loaded = [];
    }

    /** Translations straight from the language files (no admin edits). */
    public function fileLines(string $ns, string $locale): array
    {
        $file = ($this->namespaces[$ns] ?? '') . '/' . $locale . '.php';
        $lines = is_file($file) ? require $file : [];
        return is_array($lines) ? $lines : [];
    }

    public function setLocale(string $locale): void
    {
        $this->locale = preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $locale) ? $locale : 'en';
    }

    public function locale(): string
    {
        return $this->locale;
    }

    private function lines(string $ns, string $locale): array
    {
        if (!isset($this->loaded[$ns][$locale])) {
            $lines = $this->fileLines($ns, $locale);
            if ($this->overrides !== null) {
                try {
                    $lines = ($this->overrides)($ns, $locale) + $lines;
                } catch (\Throwable) {
                }
            }
            $this->loaded[$ns][$locale] = $lines;
        }
        return $this->loaded[$ns][$locale];
    }

    public function has(string $key): bool
    {
        return $this->lookup($key) !== null;
    }

    private function lookup(string $key): ?string
    {
        if (!str_contains($key, '.')) {
            return null;
        }
        [$ns, $rest] = explode('.', $key, 2);
        if (!isset($this->namespaces[$ns])) {
            return null;
        }
        foreach ([$this->locale, $this->fallback] as $loc) {
            $lines = $this->lines($ns, $loc);
            if (isset($lines[$rest])) {
                return (string) $lines[$rest];
            }
        }
        return null;
    }

    public function get(string $key, array $replace = []): string
    {
        $text = $this->lookup($key);
        if ($text === null) {
            $this->missing[$key] = true;
            return $key;
        }
        return $this->replace($text, $replace);
    }

    /** Plural form: "one item|:count items". */
    public function choice(string $key, int $count, array $replace = []): string
    {
        $text = $this->lookup($key);
        if ($text === null) {
            $this->missing[$key] = true;
            return $key;
        }
        $parts = explode('|', $text);
        $chosen = $count === 1 ? $parts[0] : ($parts[1] ?? $parts[0]);
        return $this->replace($chosen, $replace + ['count' => $count]);
    }

    private function replace(string $text, array $replace): string
    {
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        return $text;
    }

    /** Keys requested but not found (for the missing-key report). */
    public function missing(): array
    {
        return array_keys($this->missing);
    }

    /** @return string[] */
    public function availableLocales(): array
    {
        $locales = ['en'];
        foreach ($this->namespaces as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $f) {
                $locales[] = basename($f, '.php');
            }
        }
        return array_values(array_unique($locales));
    }
}
