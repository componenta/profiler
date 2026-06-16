<?php

declare(strict_types=1);

namespace Componenta\Profiler\Trigger;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Activates when any delegate trigger activates. Short-circuits on first
 * match so cheap triggers (e.g. query param) should be listed first.
 */
final readonly class CompositeTrigger implements TriggerInterface
{
    /** @var list<TriggerInterface> */
    private array $triggers;

    public function __construct(TriggerInterface ...$triggers)
    {
        $this->triggers = array_values($triggers);
    }

    public function isActive(ServerRequestInterface $request): bool
    {
        foreach ($this->triggers as $trigger) {
            if ($trigger->isActive($request)) {
                return true;
            }
        }

        return false;
    }
}
