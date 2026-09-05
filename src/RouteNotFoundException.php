<?php

declare(strict_types=1);

namespace Kasapdev\MicroRouter;

use RuntimeException;

/**
 * Thrown by Router::dispatch() when no registered route matches the
 * requested path at all (regardless of HTTP method).
 */
final class RouteNotFoundException extends RuntimeException
{
}
