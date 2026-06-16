<?php

declare(strict_types=1);

namespace Componenta\Profiler\Trigger;

use Psr\Http\Message\ServerRequestInterface;

final readonly class CookieTrigger implements TriggerInterface
{
    public function __construct(
        private string  $cookieName = '__profile',
        private ?string $expectedValue = null,
    ) {}

    public function isActive(ServerRequestInterface $request): bool
    {
        $cookies = $request->getCookieParams();

        if (!array_key_exists($this->cookieName, $cookies)) {
            return false;
        }

        if ($this->expectedValue === null) {
            return true;
        }

        $value = $cookies[$this->cookieName];

        return is_string($value) && hash_equals($this->expectedValue, $value);
    }
}
