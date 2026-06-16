<?php

declare(strict_types=1);

namespace Componenta\Profiler\Integration;

use Componenta\App\Boot\BootContext;
use Componenta\App\Boot\BootloaderInterface;
use Componenta\Profiler\ProfilerInterface;
use LogicException;

/**
 * Wraps a bootloader in a profiler span, emitted only when `boot()` actually
 * runs (i.e. the inner bootloader `supports()` the context). Attached via
 * DI delegator in {@see \Componenta\Profiler\ConfigProvider}.
 */
final readonly class TimingBootloader implements BootloaderInterface
{
    /**
     * DI decorator - {@see \Componenta\App\Runner} queries scopes on the
     * wrapped class-string directly, not on the decorator. This method
     * exists only to satisfy the interface contract.
     */
    public static function scopes(): array
    {
        throw new LogicException(
            self::class . ' is a DI decorator; query scopes on the wrapped bootloader class-string instead.'
        );
    }

    public function __construct(
        private BootloaderInterface $inner,
        private ProfilerInterface   $profiler,
        private string              $label,
    ) {}

    public function boot(BootContext $context): void
    {
        $span = $this->profiler->span($this->label);

        try {
            $this->inner->boot($context);
        } finally {
            $span->close();
        }
    }

    public function supports(BootContext $context): bool
    {
        return $this->inner->supports($context);
    }
}
