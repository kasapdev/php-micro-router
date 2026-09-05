<?php

declare(strict_types=1);

namespace Kasapdev\MicroRouter;

/**
 * A single registered route: an HTTP method, a path pattern (which may
 * contain `{param}` or `{param:regex}` placeholders), a handler, and a
 * fluent list of route-specific middleware.
 */
final class Route
{
    /** @var callable[] */
    private array $middleware = [];

    /** Compiled PCRE pattern for the path, including named capture groups. */
    private readonly string $pattern;

    /** @var string[] Ordered list of parameter names found in the path. */
    private readonly array $paramNames;

    /**
     * @param string $method  Uppercase HTTP method, or "*" to match any method.
     * @param string $path    The route path, e.g. "/users/{id}" or "/files/{path:.+}".
     * @param callable $handler The handler invoked when this route matches.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly mixed $handler
    ) {
        [$this->pattern, $this->paramNames] = self::compile($path);
    }

    /**
     * Attach one or more middleware to this specific route. Returns $this
     * for fluent chaining: $router->get(...)->middleware($a, $b).
     */
    public function middleware(callable ...$middleware): self
    {
        foreach ($middleware as $mw) {
            $this->middleware[] = $mw;
        }

        return $this;
    }

    /** @return callable[] */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Attempt to match this route's pattern against a request path.
     *
     * @return array<string,string>|null Named parameters on success, null on no match.
     */
    public function matches(string $uri): ?array
    {
        if (!preg_match($this->pattern, $uri, $matches)) {
            return null;
        }

        $params = [];
        foreach ($this->paramNames as $name) {
            if (isset($matches[$name]) && $matches[$name] !== '') {
                $params[$name] = $matches[$name];
            } elseif (array_key_exists($name, $matches)) {
                $params[$name] = $matches[$name];
            }
        }

        return $params;
    }

    /**
     * Compile a path template such as "/users/{id}/posts/{slug:[a-z0-9-]+}"
     * into an anchored PCRE pattern plus the ordered list of parameter names.
     *
     * @return array{0: string, 1: string[]}
     */
    private static function compile(string $path): array
    {
        $paramNames = [];
        $placeholders = [];
        $index = 0;

        // Replace {name} / {name:regex} tokens with unique alphanumeric
        // placeholders first, so preg_quote() below cannot mangle them.
        $withPlaceholders = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(:([^{}]+))?\}#',
            function (array $m) use (&$paramNames, &$placeholders, &$index): string {
                $name = $m[1];
                $paramNames[] = $name;
                $sub = $m[3] ?? '[^/]+';
                $token = 'ROUTEPARAMTOKEN' . $index . 'X';
                $placeholders[$token] = '(?P<' . $name . '>' . $sub . ')';
                $index++;

                return $token;
            },
            $path
        );

        $quoted = preg_quote((string) $withPlaceholders, '#');
        $regex = strtr($quoted, $placeholders);

        return ['#^' . $regex . '$#', $paramNames];
    }
}
