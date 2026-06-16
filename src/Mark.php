<?php

declare(strict_types=1);

namespace Componenta\Profiler;

/**
 * An immutable timestamped event collected by a {@see Profiler}.
 *
 * Time is captured via `hrtime(true)` (monotonic nanoseconds); memory via
 * `memory_get_usage()` so renderers can show allocation deltas alongside
 * elapsed time.
 */
final readonly class Mark
{
    public function __construct(
        public string   $label,
        public int      $tNs,
        public int      $memBytes,
        public MarkType $type = MarkType::Point,
    ) {}

    public static function now(string $label, MarkType $type = MarkType::Point): self
    {
        return new self($label, hrtime(true), memory_get_usage(), $type);
    }
}
