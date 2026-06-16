<?php

declare(strict_types=1);

namespace Componenta\Profiler\Integration;

use Componenta\Http\Middleware\PipelineFactoryInterface;
use Componenta\Http\Middleware\PipelineInterface;
use Componenta\Profiler\Http\ProfilerMiddleware;
use Componenta\Profiler\ProfilerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Decorator around {@see PipelineFactoryInterface} that:
 *
 *  1. prepends {@see ProfilerMiddleware} as the outermost middleware, so the
 *     trigger evaluation and the rendering step run before and after every
 *     other stage in the pipeline;
 *  2. wraps each remaining middleware in a {@see TimingMiddleware} so the
 *     waterfall shows per-stage elapsed time without requiring changes to
 *     the wrapped middlewares.
 *
 * Registered only by the dev-only profiler config provider; production
 * keeps the original {@see PipelineFactoryInterface} untouched.
 */
final readonly class ProfilingPipelineFactory implements PipelineFactoryInterface
{
    public function __construct(
        private PipelineFactoryInterface $inner,
        private ProfilerInterface        $profiler,
        private ProfilerMiddleware       $profilerMiddleware,
    ) {}

    public function createMiddlewarePipeline(
        iterable                 $middlewares = [],
        ?RequestHandlerInterface $fallbackHandler = null,
    ): PipelineInterface {
        $wrapped = [$this->profilerMiddleware];

        foreach ($middlewares as $middleware) {
            $wrapped[] = $this->wrap($middleware);
        }

        return $this->inner->createMiddlewarePipeline($wrapped, $fallbackHandler);
    }

    private function wrap(MiddlewareInterface $middleware): MiddlewareInterface
    {
        if ($middleware instanceof TimingMiddleware || $middleware instanceof ProfilerMiddleware) {
            return $middleware;
        }

        return new TimingMiddleware(
            $this->profiler,
            $middleware,
            $this->shortClassName($middleware::class),
        );
    }

    private function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
