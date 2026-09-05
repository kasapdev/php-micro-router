<?php

declare(strict_types=1);

namespace Kasapdev\MicroRouter;

/**
 * A minimal HTTP router: static + parameterized paths, nested route groups,
 * global and per-route middleware, and an onion-style dispatch pipeline.
 *
 * The request passed to handlers/middleware is a plain array (no PSR-7
 * dependency) shaped like:
 *   [
 *       'method' => 'GET',
 *       'uri'    => '/users/42?foo=bar',
 *       'path'   => '/users/42',
 *       'params' => ['id' => '42'],
 *       'query'  => ['foo' => 'bar'],
 *   ]
 */
final class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var callable[] */
    private array $globalMiddleware = [];

    /** @var string[] */
    private array $groupPrefixStack = [];

    public function get(string $path, callable $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /** Register a route that matches any HTTP method. */
    public function any(string $path, callable $handler): Route
    {
        return $this->addRoute('*', $path, $handler);
    }

    /**
     * Group routes under a common path prefix. Groups may be nested;
     * prefixes are concatenated in registration order.
     */
    public function group(string $prefix, callable $callback): void
    {
        $this->groupPrefixStack[] = trim($prefix, '/');

        try {
            $callback($this);
        } finally {
            array_pop($this->groupPrefixStack);
        }
    }

    /** Register global middleware, run (in registration order) before route-specific middleware. */
    public function use(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    private function addRoute(string $method, string $path, callable $handler): Route
    {
        $fullPath = $this->currentPrefix() . '/' . ltrim($path, '/');
        $fullPath = '/' . ltrim($fullPath, '/');
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        $route = new Route($method === '*' ? '*' : strtoupper($method), $fullPath, $handler);
        $this->routes[] = $route;

        return $route;
    }

    private function currentPrefix(): string
    {
        if ($this->groupPrefixStack === []) {
            return '';
        }

        $segments = array_filter($this->groupPrefixStack, static fn (string $p): bool => $p !== '');

        return $segments === [] ? '' : '/' . implode('/', $segments);
    }

    /**
     * Dispatch a method + URI through the matching route's middleware
     * pipeline and handler, returning whatever the handler returns.
     *
     * @throws RouteNotFoundException    if no route matches the path at all.
     * @throws MethodNotAllowedException if the path matches but not the method.
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $method = strtoupper($method);

        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        if ($path === '') {
            $path = '/';
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $queryString = parse_url($uri, PHP_URL_QUERY);
        $query = [];
        if (is_string($queryString)) {
            parse_str($queryString, $query);
        }

        $pathMatchedAnyMethod = false;
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $params = $route->matches($path);
            if ($params === null) {
                continue;
            }

            $pathMatchedAnyMethod = true;

            if ($route->method !== '*' && $route->method !== $method) {
                $allowedMethods[] = $route->method;
                continue;
            }

            $request = [
                'method' => $method,
                'uri' => $uri,
                'path' => $path,
                'params' => $params,
                'query' => $query,
            ];

            $pipeline = array_merge($this->globalMiddleware, $route->getMiddleware());

            return $this->runPipeline($pipeline, $request, $route->handler);
        }

        if ($pathMatchedAnyMethod) {
            throw new MethodNotAllowedException(
                sprintf(
                    'Method %s not allowed for %s. Allowed methods: %s',
                    $method,
                    $path,
                    implode(', ', array_values(array_unique($allowedMethods)))
                ),
                array_values(array_unique($allowedMethods))
            );
        }

        throw new RouteNotFoundException(sprintf('No route found for %s %s', $method, $path));
    }

    /**
     * Build and run the onion-style middleware pipeline ending in $handler.
     *
     * @param callable[] $pipeline
     */
    private function runPipeline(array $pipeline, array $request, callable $handler): mixed
    {
        $next = static function (array $request) use (&$pipeline, &$next, $handler): mixed {
            $middleware = array_shift($pipeline);

            if ($middleware === null) {
                return $handler($request);
            }

            return $middleware($request, $next);
        };

        return $next($request);
    }
}
