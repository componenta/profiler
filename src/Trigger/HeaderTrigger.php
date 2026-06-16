<?php

declare(strict_types=1);

namespace Componenta\Profiler\Trigger;

use Psr\Http\Message\ServerRequestInterface;

final readonly class HeaderTrigger implements TriggerInterface
{
    public function __construct(
        private string  $headerName = 'X-Profile',
        private ?string $expectedValue = null,
    ) {}

    public function isActive(ServerRequestInterface $request): bool
    {
        if (!$request->hasHeader($this->headerName)) {
            return false;
        }

        if ($this->expectedValue === null) {
            return true;
        }

        return hash_equals(
            $this->expectedValue,
            $request->getHeaderLine($this->headerName),
        );
    }
}
