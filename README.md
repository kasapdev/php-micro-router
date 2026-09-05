# php-micro-router

[![CI](https://github.com/kasapdev/php-micro-router/actions/workflows/ci.yml/badge.svg)](https://github.com/kasapdev/php-micro-router/actions/workflows/ci.yml)

A tiny, zero-dependency PHP router. Static and parameterized paths (`{id}`, or `{id:\d+}` with a
custom regex constraint), nested route groups, global and per-route middleware built as an
onion-style pipeline, and no PSR-7 dependency — the request handed to handlers and middleware is
a plain array.

## Installation

Once published to Packagist:

```bash
composer require kasapdev/php-micro-router
```

Until then (or if you'd rather not wait on Packagist), just require the files directly:

```php
require_once 'src/Route.php';
require_once 'src/Router.php';
require_once 'src/RouteNotFoundException.php';
require_once 'src/MethodNotAllowedException.php';
```

## Usage

```php
use Kasapdev\MicroRouter\Router;
use Kasapdev\MicroRouter\RouteNotFoundException;
use Kasapdev\MicroRouter\MethodNotAllowedException;

$router = new Router();

// Global middleware (runs for every request, outermost layer of the onion).
$router->use(function (array $request, callable $next) {
    $start = microtime(true);
    $response = $next($request);
    error_log(sprintf('%s %s took %.2fms', $request['method'], $request['path'], (microtime(true) - $start) * 1000));
    return $response;
});

$router->get('/', fn (array $req) => 'welcome');

// {id} matches a single path segment.
$router->get('/users/{id}', function (array $req) {
    return 'user #' . $req['params']['id'];
});

// {slug:regex} constrains the segment to a custom pattern.
$router->get('/posts/{id:\d+}', fn (array $req) => 'post #' . $req['params']['id']);

// Route groups share a path prefix and can be nested.
$router->group('/api', function (Router $api) {
    $api->group('/v1', function (Router $v1) {
        $v1->get('/users', fn () => ['alice', 'bob']);

        // Per-route middleware, attached fluently on the Route object.
        $v1->post('/users', function (array $req) {
            return 'created';
        })->middleware(function (array $req, callable $next) {
            if (empty($req['query']['token'])) {
                return ['error' => 'unauthorized'];
            }
            return $next($req);
        });
    });
});

// Dispatch a method + URI (as you'd get from $_SERVER['REQUEST_METHOD'] / REQUEST_URI).
try {
    $result = $router->dispatch('GET', '/users/42');
    echo $result;
} catch (RouteNotFoundException $e) {
    http_response_code(404);
    echo 'Not found';
} catch (MethodNotAllowedException $e) {
    http_response_code(405);
    echo 'Allowed: ' . implode(', ', $e->getAllowedMethods());
}
```

### The request array

Handlers and middleware receive a plain array, not an object:

```php
[
    'method' => 'GET',
    'uri'    => '/users/42?active=1',
    'path'   => '/users/42',
    'params' => ['id' => '42'],
    'query'  => ['active' => '1'],
]
```

### Middleware signature

```php
function (array $request, callable $next): mixed {
    // ... do something before ...
    $response = $next($request); // pass control (and optionally a mutated $request) downstream
    // ... do something after ...
    return $response;
}
```

A middleware can short-circuit the pipeline simply by returning without calling `$next()`.

## API

### `Router`

- `get/post/put/patch/delete(string $path, callable $handler): Route`
- `any(string $path, callable $handler): Route` — matches any HTTP method
- `group(string $prefix, callable $callback): void` — `$callback` receives the `Router` instance; groups nest
- `use(callable $middleware): void` — registers global middleware
- `dispatch(string $method, string $uri): mixed` — runs the pipeline for the matching route and returns its result

### `Route`

- `middleware(callable ...$middleware): self` — fluent, attaches route-specific middleware

### Exceptions

- `RouteNotFoundException` — no route matches the path
- `MethodNotAllowedException` — path matches, method doesn't; `getAllowedMethods(): array` lists what would have matched

## Testing

```bash
php tests/run.php
```

## License

MIT
