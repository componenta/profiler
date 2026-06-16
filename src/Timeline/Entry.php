<?php

declare(strict_types=1);

namespace Componenta\Profiler\Timeline;

/**
 * A single row in the flattened timeline view of a profiler run.
 *
 * Points come from `mark()` and hold only `tNs` / `memBytes`. Spans come from
 * `span()` - `durationNs`, `selfNs`, and `memDeltaBytes` are populated from
 * the matching `end` mark. `depth` is the nesting level at the time the entry
 * was opened; it drives indentation in text renderers and grouping in JSON
 * output.
 *
 * `selfNs` is the exclusive (self) time - `durationNs` minus the sum of
 * direct-child span durations. It's the number that actually answers "how
 * much work did this entry do on its own", which matters for PSR-15
 * middleware timing where outer stages otherwise appear to dominate.
 */
final readonly class Entry
{
    public function __construct(
        public EntryKind $kind,
        public string    $label,
        public int       $tNs,
        public int       $memBytes,
        public int       $depth,
        public ?int      $durationNs = null,
        public ?int      $selfNs = null,
        public ?int      $memDeltaBytes = null,
    ) {}
}
