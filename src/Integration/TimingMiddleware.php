<?php

declare(strict_types=1);

namespace Componenta\Profiler\Integration;

use Componenta\Profiler\ProfilerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Wraps a middleware in a profiler span.
 *
 * Produced by {@see ProfilingPipelineFactory} for every middleware in the
 * pipeline so the waterfall shows per-middleware times without touching
 * the original implementations.
 */
final readonly class TimingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProfilerInterface   $profiler,
        private MiddlewareInterface $inner,
        private string              $label,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $span = $this->profiler->span($this->label);

        try {
            return $this->inner->process($request, $handler);
        } finally {
            $span->close();
        }
    }
}
