<?php

declare(strict_types=1);

namespace Componenta\Profiler;

use Componenta\Stdlib\PathResolverInterface;
use Componenta\Http\Middleware\PipelineFactoryInterface;
use Componenta\Profiler\Http\ProfilerMiddleware;
use Componenta\Profiler\Integration\ClassProxyGenerator;
use Componenta\Profiler\Integration\ProfilingPipelineFactory;
use Componenta\Profiler\Renderer\CompositeRenderer;
use Componenta\Profiler\Renderer\JsonRenderer;
use Componenta\Profiler\Renderer\RendererInterface;
use Componenta\Profiler\Renderer\ServerTimingRenderer;
use Componenta\Profiler\Renderer\WaterfallRenderer;
use Componenta\Profiler\Trigger\CompositeTrigger;
use Componenta\Profiler\Trigger\HeaderTrigger;
use Componenta\Profiler\Trigger\QueryParamTrigger;
use Componenta\Profiler\Trigger\TriggerInterface;
use Componenta\Config\Config;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Registers profiler services for DI.
 *
 * Intended to be included only from the development branch of the host
 * application's root config - the composer package itself is `require-dev`.
 *
 * Key wiring points:
 *  - {@see ProfilerInterface} aliased to a shared {@see Profiler} instance
 *  - {@see RendererInterface} aliased to a composite that emits both a
 *    waterfall body and a `Server-Timing` header (a sensible default; apps
 *    can override the alias to pick a single renderer)
 *  - {@see TriggerInterface} aliased to a default query/header composite
 *  - {@see PipelineFactoryInterface} decorated to install
 *    {@see ProfilingPipelineFactory}, which prepends {@see ProfilerMiddleware}
 *    and wraps each middleware for per-stage timing
 *
 * Profiled targets (specific classes, bootloaders) are *not* configured here
 * - that is application policy and belongs in the app-level dev config.
 * Use {@see profile_classes()} / {@see time_bootloaders()} from
 * `functions.php` when writing those delegators.
 */
final class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            ConfigKey::ROOT => [
                ConfigKey::PROXY_CACHE_DIR => self::defaultProxyCacheDir(),
            ],
        ];
    }

    protected function getAliases(): array
    {
        return [
            ProfilerInterface::class => Profiler::class,
            TriggerInterface::class  => CompositeTrigger::class,
            RendererInterface::class => CompositeRenderer::class,
        ];
    }

    protected function getInvokables(): array
    {
        return [
            Profiler::class,
        ];
    }

    protected function getAutowires(): array
    {
        return [
            WaterfallRenderer::class,
            JsonRenderer::class,
            ServerTimingRenderer::class,
            ProfilerMiddleware::class,
            Integration\ClassProfilerDelegator::class,
            Integration\BootloaderTimingDelegator::class,
        ];
    }

    protected function getFactories(): array
    {
        return [
            QueryParamTrigger::class => static fn () => new QueryParamTrigger(),
            HeaderTrigger::class     => static fn () => new HeaderTrigger(),

            CompositeTrigger::class  => static fn (ContainerInterface $c) => new CompositeTrigger(
                $c->get(QueryParamTrigger::class),
                $c->get(HeaderTrigger::class),
            ),

            CompositeRenderer::class => static fn (ContainerInterface $c) => new CompositeRenderer(
                $c->get(WaterfallRenderer::class),
                $c->get(ServerTimingRenderer::class),
            ),

            WaterfallRenderer::class => static fn (ContainerInterface $c) => new WaterfallRenderer(
                $c->get(StreamFactoryInterface::class),
            ),

            JsonRenderer::class => static fn (ContainerInterface $c) => new JsonRenderer(
                $c->get(StreamFactoryInterface::class),
            ),

            ServerTimingRenderer::class => static fn () => new ServerTimingRenderer(),

            ClassProxyGenerator::class => static function (ContainerInterface $c): ClassProxyGenerator {
                $profiler = $c->get(Config::class)->get(ConfigKey::ROOT, default: []);
                $paths = $c->get(PathResolverInterface::class);

                // Inlined default - `self::defaultProxyCacheDir()` would
                // crash after `Export::pretty()` round-trips this closure
                // to the config cache: the exported source loses its
                // lexical class binding and `self` resolves to nothing.
                $dir = $profiler[ConfigKey::PROXY_CACHE_DIR]
                    ?? 'var/cache/profiler/proxies';

                return new ClassProxyGenerator($paths->resolve($dir));
            },
        ];
    }

    protected function getDelegators(): array
    {
        return [
            PipelineFactoryInterface::class => [
                // Closure stays in-memory only; profiler is a dev-only package
                // and dev configs are not var_export-cached.
                static fn (PipelineFactoryInterface $inner, ContainerInterface $c): PipelineFactoryInterface
                    => new ProfilingPipelineFactory(
                        $inner,
                        $c->get(ProfilerInterface::class),
                        $c->get(ProfilerMiddleware::class),
                    ),
            ],
        ];
    }

    /**
     * Default proxy-cache directory.
     *
     * Used at config-assembly time (see {@see getConfig()}); inside
     * exported closures the equivalent expression is inlined to avoid
     * depending on `self::` after the closure is serialised.
     */
    private static function defaultProxyCacheDir(): string
    {
        return 'var/cache/profiler/proxies';
    }
}
