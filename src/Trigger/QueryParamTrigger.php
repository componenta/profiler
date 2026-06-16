<?php

declare(strict_types=1);

namespace Componenta\Profiler\Trigger;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Activates when a specific query parameter is present on the request.
 *
 * Matching is presence-based by default so `?__profile` is enough. Pass a
 * non-empty `$expectedValue` to require an exact match (useful if a build
 * ever leaks into a semi-public environment - treat the value as a guard
 * string, not a cryptographic token).
 */
final readonly class QueryParamTrigger implements TriggerInterface
{
    public function __construct(
        private string  $paramName = '__profile',
        private ?string $expectedValue = null,
    ) {}

    public function isActive(ServerRequestInterface $request): bool
    {
        $params = $request->getQueryParams();

        if (!array_key_exists($this->paramName, $params)) {
            return false;
        }

        if ($this->expectedValue === null) {
            return true;
        }

        $value = $params[$this->paramName];

        return is_string($value) && hash_equals($this->expectedValue, $value);
    }
}
