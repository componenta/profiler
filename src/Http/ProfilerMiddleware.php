<?php

declare(strict_types=1);

namespace Componenta\Profiler\Http;

use Componenta\Profiler\ProfilerInterface;
use Componenta\Profiler\Renderer\RendererInterface;
use Componenta\Profiler\Timeline\Timeline;
use Componenta\Profiler\Trigger\TriggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Outermost profiler middleware. Runs first in the pipeline so the span
 * encompasses every other middleware and the handler.
 *
 * When no trigger activates, the middleware costs one virtual call per
 * trigger and a `span()`/`close()` pair on the profiler; the profiler
 * itself remains collecting (marks left by decorators deeper in the
 * pipeline stay accessible to the dev for ad-hoc inspection, but no HTTP
 * output is produced).
 *
 * On activation, the response passes through the configured renderer
 * before being returned to the emitter - renderers may replace the body
 * (waterfall/JSON) or piggy-back headers (Server-Timing).
 */
final readonly class ProfilerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProfilerInterface $profiler,
        private TriggerInterface  $trigger,
        private RendererInterface $renderer,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $span = $this->profiler->span('http.request');

        try {
            $response = $handler->handle($request);
        } finally {
            $span->close();
        }

        if (!$this->trigger->isActive($request)) {
            return $response;
        }

        return $this->renderer->render(
            Timeline::fromProfiler($this->profiler),
            $response,
        );
    }
}
