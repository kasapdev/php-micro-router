<?php

declare(strict_types=1);

namespace Kasapdev\MicroRouter;

use RuntimeException;

/**
 * Thrown by Router::dispatch() when the requested path matches one or more
 * registered routes, but none of them accept the requested HTTP method.
 */
final class MethodNotAllowedException extends RuntimeException
{
    /** @var string[] */
    private array $allowedMethods;

    public function __construct(string $message, array $allowedMethods = [])
    {
        parent::__construct($message);
        $this->allowedMethods = $allowedMethods;
    }

    /** @return string[] */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
