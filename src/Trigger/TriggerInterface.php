<?php

declare(strict_types=1);

namespace Componenta\Profiler\Trigger;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Decides whether the profiler should activate for a given request.
 *
 * Triggers are asked once per request by
 * {@see \Componenta\Profiler\Http\ProfilerMiddleware} before any measurement
 * takes place. When every trigger returns false, the middleware forwards
 * the request unmodified - inactive-path overhead is a single virtual
 * call per trigger.
 */
interface TriggerInterface
{
    public function isActive(ServerRequestInterface $request): bool;
}
