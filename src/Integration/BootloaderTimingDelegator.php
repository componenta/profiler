<?php

declare(strict_types=1);

namespace Componenta\Profiler\Integration;

use Componenta\App\Boot\BootloaderInterface;
use Componenta\Profiler\ProfilerInterface;
use Psr\Container\ContainerInterface;

/**
 * DI delegator that wraps a bootloader in {@see TimingBootloader}. Register
 * once as an invokable service; the container resolves the profiler
 * dependency, and the delegator's `__invoke` supplies the bootloader
 * identity as the span label.
 */
final readonly class BootloaderTimingDelegator
{
    public function __construct(
        private ProfilerInterface $profiler,
    ) {}

    public function __invoke(BootloaderInterface $entry, ContainerInterface $container): BootloaderInterface
    {
        return new TimingBootloader(
            $entry,
            $this->profiler,
            $this->shortClassName($entry::class),
        );
    }

    private function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
