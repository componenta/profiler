<?php

declare(strict_types=1);

namespace Componenta\Profiler\Integration;

use Componenta\Profiler\ProfilerInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * DI delegator that wraps a freshly-built entry in a profiling subclass
 * produced by {@see ClassProxyGenerator}.
 *
 * Usage in a dev-only config provider's `delegators` section:
 *
 * ```
 * 'delegators' => [
 *     PostRepository::class => [ClassProfilerDelegator::class],
 * ]
 * ```
 *
 * The delegator is built by the container once per entry and reused for
 * every resolution. Proxies are cached by target class - the Reflection
 * cost is paid on first resolve of a given target, not per instance.
 */
final class ClassProfilerDelegator
{
    /** @var array<class-string, class-string> */
    private array $proxyFqcnByTarget = [];

    public function __construct(
        private readonly ClassProxyGenerator $generator,
        private readonly ProfilerInterface   $profiler,
    ) {}

    /**
     * Signature matches Componenta DI's delegator callable: `($entry, $container)`.
     */
    public function __invoke(object $entry, ContainerInterface $container): object
    {
        $targetClass = $entry::class;
        $proxyClass  = $this->proxyFqcnByTarget[$targetClass]
            ??= $this->generator->generate($targetClass);

        $this->ensureProxyStaticsBound($proxyClass, $targetClass);

        return $this->transplant($entry, $proxyClass);
    }

    /**
     * @param class-string $proxyClass
     * @param class-string $targetClass
     */
    private function ensureProxyStaticsBound(string $proxyClass, string $targetClass): void
    {
        // Static properties initialise to declared default (null / empty) -
        // setting them here is idempotent and keeps the proxy source free of
        // container awareness.
        $proxyClass::$__profiler = $this->profiler;
        $proxyClass::$__label    = $this->shortClassName($targetClass);
    }

    /**
     * @param class-string $proxyClass
     */
    private function transplant(object $target, string $proxyClass): object
    {
        $proxyReflection = new ReflectionClass($proxyClass);
        $proxy           = $proxyReflection->newInstanceWithoutConstructor();

        foreach ($this->parentProperties($proxyReflection) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (!$property->isInitialized($target)) {
                continue;
            }

            try {
                $property->setValue($proxy, $property->getValue($target));
            } catch (\Throwable $e) {
                throw new RuntimeException(sprintf(
                    'Failed to transplant property $%s from %s to profiler proxy: %s',
                    $property->name,
                    $target::class,
                    $e->getMessage(),
                ), previous: $e);
            }
        }

        return $proxy;
    }

    /**
     * @return iterable<ReflectionProperty>
     */
    private function parentProperties(ReflectionClass $proxyReflection): iterable
    {
        $parent = $proxyReflection->getParentClass();

        while ($parent !== false) {
            foreach ($parent->getProperties() as $property) {
                yield $property;
            }

            $parent = $parent->getParentClass();
        }
    }

    private function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
