<?php
declare(strict_types=1);

namespace AP\Core;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

final class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];
    /** @var array<string, string> name => pattern */
    private array $named = [];
    private array $groupStack = [];
    private string $currentModule = '';
    /** @var array<string, class-string> */
    private array $aliases = [];

    public function alias(string $name, string $class): void
    {
        $this->aliases[$name] = $class;
    }

    public function setModule(string $module): void
    {
        $this->currentModule = $module;
    }

    public function group(array $options, callable $callback): void
    {
        $this->groupStack[] = $options;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler, $name, $middleware);
    }

    public function post(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['POST'], $pattern, $handler, $name, $middleware);
    }

    public function any(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $pattern, $handler, $name, $middleware);
    }

    public function add(array $methods, string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $prefix = '';
        $mw = [];
        $namePrefix = '';
        foreach ($this->groupStack as $g) {
            $prefix .= '/' . trim((string) ($g['prefix'] ?? ''), '/');
            $mw = array_merge($mw, (array) ($g['middleware'] ?? []));
            $namePrefix .= (string) ($g['as'] ?? '');
        }
        $full = '/' . trim($prefix . '/' . trim($pattern, '/'), '/');
        $full = preg_replace('#/+#', '/', $full) ?: '/';
        if ($name !== null) {
            $name = $namePrefix . $name;
            $this->named[$name] = $full;
        }
        $this->routes[] = [
            'methods' => $methods,
            'pattern' => $full,
            'regex' => self::compile($full),
            'handler' => $handler,
            'name' => $name,
            'middleware' => array_merge($mw, $middleware),
            'module' => $this->currentModule,
        ];
    }

    /**
     * A route tried only after every normal route, whatever order modules load in.
     * Used for catch-all addresses such as content pages: $r->fallback('/{path:.+}', [PageController::class, 'show'], 'page');
     */
    public function fallback(string $pattern, mixed $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler, $name, $middleware);
        $this->routes[array_key_last($this->routes)]['fallback'] = true;
    }

    /** First path segments that normal routes use ("admin", "install", "cron"...), so content cannot take them. @return list<string> */
    public function reservedSegments(): array
    {
        $out = [];
        foreach ($this->routes as $route) {
            if (!empty($route['fallback'])) {
                continue;
            }
            $first = explode('/', trim((string) $route['pattern'], '/'))[0] ?? '';
            if ($first !== '' && !str_contains($first, '{')) {
                $out[$first] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Splits a pattern into literal text and {name} / {name:regex} parameters. The regex may contain its own braces,
     * such as {code:[a-z]{2,3}} or {token:[a-f0-9]{32}}.
     * @return list<array{0:string,1:string,2:?string}> [kind ("text" or "param"), text or name, regex]
     */
    public static function parts(string $pattern): array
    {
        $out = [];
        $len = strlen($pattern);
        $text = '';
        for ($i = 0; $i < $len; $i++) {
            $c = $pattern[$i];
            if ($c === '{' && preg_match('/\G\{(\w+)(:|\})/', $pattern, $m, 0, $i)) {
                if ($text !== '') {
                    $out[] = ['text', $text, null];
                    $text = '';
                }
                $i += strlen($m[0]);
                if ($m[2] === '}') {
                    $out[] = ['param', $m[1], null];
                    $i--;
                    continue;
                }
                $depth = 0;
                $regex = '';
                for (; $i < $len; $i++) {
                    $ch = $pattern[$i];
                    if ($ch === '\\' && $i + 1 < $len) {
                        $regex .= $ch . $pattern[++$i];
                        continue;
                    }
                    if ($ch === '{') {
                        $depth++;
                    } elseif ($ch === '}') {
                        if ($depth === 0) {
                            break;
                        }
                        $depth--;
                    }
                    $regex .= $ch;
                }
                $out[] = ['param', $m[1], $regex];
                continue;
            }
            $text .= $c;
        }
        if ($text !== '') {
            $out[] = ['text', $text, null];
        }
        return $out;
    }

    public static function compile(string $pattern): string
    {
        $regex = '';
        foreach (self::parts($pattern) as [$kind, $value, $re]) {
            $regex .= $kind === 'text' ? preg_quote($value, '#') : '(?P<' . $value . '>' . ($re ?? '[^/]+') . ')';
        }
        return '#^' . $regex . '$#u';
    }

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException("Unknown route: {$name}");
        }
        $url = '';
        foreach (self::parts($this->named[$name]) as [$kind, $value]) {
            if ($kind === 'text') {
                $url .= $value;
                continue;
            }
            $url .= rawurlencode((string) ($params[$value] ?? ''));
            unset($params[$value]);
        }
        $url = $url !== '' ? $url : '/';
        return $params ? $url . '?' . http_build_query($params) : $url;
    }

    public function hasRoute(string $name): bool
    {
        return isset($this->named[$name]);
    }

    public function routes(): array
    {
        return $this->routes;
    }

    /** @return array{0: array<string,mixed>, 1: array<string,string>}|null */
    public function match(string $method, string $path): ?array
    {
        $pathAllowed = false;
        // Normal routes first, then fallbacks.
        $ordered = array_merge(
            array_filter($this->routes, static fn($r) => empty($r['fallback'])),
            array_filter($this->routes, static fn($r) => !empty($r['fallback']))
        );
        foreach ($ordered as $route) {
            if (!empty($route['fallback']) && $pathAllowed) {
                break;
            }
            if (preg_match($route['regex'], $path, $m)) {
                $pathAllowed = true;
                if (in_array($method, $route['methods'], true)) {
                    $params = array_filter($m, static fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
                    return [$route, $params];
                }
            }
        }
        if ($pathAllowed) {
            throw new HttpException(405);
        }
        return null;
    }

    public function dispatch(Request $request): Response
    {
        $found = $this->match($request->method, $request->path);
        if ($found === null) {
            throw new HttpException(404);
        }
        [$route, $params] = $found;
        $request->params = $params;
        $request->attributes['route'] = $route;

        $core = fn(Request $req): Response => $this->invoke($route['handler'], $req, $params);
        $pipeline = array_reduce(
            array_reverse($route['middleware']),
            function (callable $next, string $spec): callable {
                [$class, $args] = $this->resolveMiddleware($spec);
                return static fn(Request $req): Response => (new $class())->handle($req, $next, $args);
            },
            $core
        );
        return $pipeline($request);
    }

    /** @return array{0: class-string, 1: array<int,string>} */
    private function resolveMiddleware(string $spec): array
    {
        $name = $spec;
        $args = [];
        if (str_contains($spec, ':')) {
            [$name, $argString] = explode(':', $spec, 2);
            $args = explode(',', $argString);
        }
        $class = $this->aliases[$name] ?? $name;
        if (!class_exists($class)) {
            throw new \RuntimeException("Middleware not found: {$spec}");
        }
        return [$class, $args];
    }

    public static function normalise(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || $result instanceof \JsonSerializable) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    private function invoke(mixed $handler, Request $request, array $params): Response
    {
        if ($handler instanceof Closure) {
            $ref = new ReflectionFunction($handler);
            return self::normalise($handler(...$this->arguments($ref->getParameters(), $request, $params)));
        }
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $object = is_object($class) ? $class : new $class();
            $ref = new ReflectionMethod($object, $method);
            return self::normalise($ref->invokeArgs($object, $this->arguments($ref->getParameters(), $request, $params)));
        }
        throw new \RuntimeException('Invalid route handler.');
    }

    /** @param \ReflectionParameter[] $parameters */
    private function arguments(array $parameters, Request $request, array $params): array
    {
        $args = [];
        foreach ($parameters as $p) {
            $type = $p->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
            if ($typeName === Request::class) {
                $args[] = $request;
            } elseif (array_key_exists($p->getName(), $params)) {
                $v = $params[$p->getName()];
                $args[] = $typeName === 'int' ? (int) $v : $v;
            } elseif ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        return $args;
    }
}
