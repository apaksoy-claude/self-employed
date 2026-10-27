<?php
declare(strict_types=1);

namespace AP\Core;

/**
 * Validation. Rules: required, nullable, string, email, url, numeric, integer, min:n, max:n,
 * between:a,b, in:a,b, same:field, confirmed, regex:/x/, alpha_num, unique:table,column[,ignoreId], exists:table,column.
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];

    /** @param array<string, string|array<int,string>> $rules */
    public function __construct(private array $data, private array $rules, private array $labels = [])
    {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return (new self($data, $rules, $labels))->run();
    }

    public function run(): self
    {
        $this->errors = [];
        $this->validated = [];
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            $nullable = in_array('nullable', $rules, true);
            $isEmpty = $value === null || $value === '' || $value === [];
            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->fail($field, 'required');
                } elseif (!$nullable) {
                    $this->validated[$field] = $value;
                } else {
                    $this->validated[$field] = null;
                }
                continue;
            }
            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, '');
                if (!$this->check($name, $arg, $field, $value)) {
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
        return $this;
    }

    private function check(string $name, string $arg, string $field, mixed $value): bool
    {
        $str = is_scalar($value) ? (string) $value : '';
        $params = $arg === '' ? [] : explode(',', $arg);
        $ok = match ($name) {
            'string' => is_string($value),
            'email' => (bool) filter_var($str, FILTER_VALIDATE_EMAIL),
            'url' => (bool) filter_var($str, FILTER_VALIDATE_URL),
            'ip' => filter_var($str, FILTER_VALIDATE_IP) !== false,
            'numeric' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'alpha_num' => (bool) preg_match('/^[A-Za-z0-9_-]+$/', $str),
            'min' => $this->size($value) >= (float) $arg,
            'max' => $this->size($value) <= (float) $arg,
            'between' => $this->size($value) >= (float) ($params[0] ?? 0) && $this->size($value) <= (float) ($params[1] ?? 0),
            'in' => in_array($str, $params, true),
            'same' => ($this->data[$arg] ?? null) === $value,
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value,
            'regex' => (bool) @preg_match($arg, $str),
            'unique' => $this->unique($params, $str),
            'exists' => $this->exists($params, $str),
            default => true,
        };
        if (!$ok) {
            $this->fail($field, $name, ['min' => $arg, 'max' => $arg, 'other' => $arg, 'values' => implode(', ', $params)]);
        }
        return $ok;
    }

    private function size(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : (float) mb_strlen((string) $v);
    }

    private function unique(array $p, string $value): bool
    {
        try {
            $db = App::instance()->make(DB::class);
            $q = $db->table($p[0])->where($p[1] ?? 'id', $value);
            if (isset($p[2]) && $p[2] !== '') {
                $q->where('id', '!=', (int) $p[2]);
            }
            return !$q->exists();
        } catch (\Throwable) {
            return true;
        }
    }

    private function exists(array $p, string $value): bool
    {
        try {
            return App::instance()->make(DB::class)->table($p[0])->where($p[1] ?? 'id', $value)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function fail(string $field, string $rule, array $extra = []): void
    {
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $key = 'validation.' . $rule;
        $t = app_translator();
        $message = $t->has($key) ? $t->get($key, ['attribute' => $label] + $extra) : "{$label} is invalid ({$rule}).";
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    public function validated(): array
    {
        return $this->validated;
    }
}
