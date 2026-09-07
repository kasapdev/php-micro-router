<?php

declare(strict_types=1);

namespace Kasapdev\MicroRouter;

use RuntimeException;

/**
 * Thrown by Route::buildUrl() (via Router::url()) when the params array
 * passed in is missing a value for a `{param}` placeholder required by the
 * route's path template.
 */
final class MissingRouteParameterException extends RuntimeException
{
}
