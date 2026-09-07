# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.2.0] - 2026-09-07

### Added

- Named-route URL generation:
  - `Route::name(string $name): static` — fluent, names a route so it can be
    looked up later: `$router->get('/users/{id}', $handler)->name('user.show')`.
  - `Route::getName(): ?string` — returns the assigned name, or `null`.
  - `Router::url(string $name, array $params = []): string` — builds the real
    path for a named route by substituting `{param}` / `{param:regex}`
    placeholders with values from `$params`, reusing the exact same
    placeholder-parsing regex the router already uses for path matching.
  - `Route::buildUrl(array $params): string` — the underlying substitution
    logic, exposed on `Route` directly.
  - New `MissingRouteParameterException` (extends `RuntimeException`, same
    pattern as the existing exceptions), thrown by `buildUrl()`/`url()` when
    a required placeholder has no corresponding entry in `$params`.
  - `Router::url()` throws the existing `RouteNotFoundException` when called
    with a name that has no matching registered route.
- Tests in `tests/run.php` covering: building a URL from a named route,
  substituting multiple placeholders (including a regex-constrained one),
  named routes registered inside a group, a missing required param throwing
  `MissingRouteParameterException`, and an unknown route name throwing
  `RouteNotFoundException`.
- `## Named Routes` section in `README.md` with a runnable example, plus
  updated `API` and `Installation` sections.

## [1.1.0] - 2026-09-06

### Added

- Test coverage for edge cases in `Router` and `Route` that were previously
  documented behavior but untested:
  - Static path segments containing regex-special characters (e.g. the `.`
    in `/status.json`) are correctly escaped via `preg_quote()` and matched
    literally, not treated as a regex wildcard.
  - `Router::dispatch()` accepts a lowercase HTTP method (e.g. `get`) and
    still matches routes registered with `->get()`, confirming the
    documented case-insensitive method matching.
  - Query strings using PHP's array syntax (e.g. `?tags[]=a&tags[]=b`) are
    parsed into a real PHP array via `parse_str()`, not just scalar values.
  - `Router::group()` called with an empty-string prefix does not introduce
    a doubled slash into the resulting route path.

No behavioral changes were needed — all new edge-case tests passed against
the existing implementation.
