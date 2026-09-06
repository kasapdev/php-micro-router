# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
