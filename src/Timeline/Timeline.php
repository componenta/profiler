<?php

declare(strict_types=1);

namespace Componenta\Profiler\Timeline;

use Componenta\Profiler\Mark;
use Componenta\Profiler\MarkType;
use Componenta\Profiler\ProfilerInterface;

/**
 * Projection of a profiler's flat mark stream into the hierarchical view a
 * renderer actually wants: points and spans ordered by start time, with
 * durations and depth pre-computed.
 *
 * The reduction is stable even when spans never close (e.g. because an
 * exception short-circuited the request): a missing `end` leaves the span
 * with `durationNs = null`, which renderers flag visibly instead of skipping.
 * This intentionally forgives mismatched `begin`/`end` pairs rather than
 * throwing - a profiler that crashes its own report is useless.
 */
final readonly class Timeline
{
    /**
     * @param list<Entry> $entries
     */
    public function __construct(
        public int   $startNs,
        public int   $endNs,
        public int   $peakMemoryBytes,
        public array $entries,
    ) {}

    public static function fromProfiler(ProfilerInterface $profiler): self
    {
        return self::build(
            $profiler->marks(),
            $profiler->peakMemoryBytes(),
        );
    }

    /**
     * @param list<Mark> $marks
     */
    public static function build(array $marks, int $peakMemoryBytes): self
    {
        if ($marks === []) {
            return new self(0, 0, $peakMemoryBytes, []);
        }

        $entries   = [];
        $openIdx   = [];
        /** @var array<int, int> Accumulated direct-children duration per open-span index. */
        $childTime = [];
        $depth     = 0;
        $startNs   = $marks[0]->tNs;
        $endNs     = $marks[0]->tNs;

        foreach ($marks as $mark) {
            if ($mark->tNs > $endNs) {
                $endNs = $mark->tNs;
            }

            switch ($mark->type) {
                case MarkType::Point:
                    $entries[] = new Entry(
                        kind:     EntryKind::Point,
                        label:    $mark->label,
                        tNs:      $mark->tNs,
                        memBytes: $mark->memBytes,
                        depth:    $depth,
                    );
                    break;

                case MarkType::Begin:
                    $entries[] = new Entry(
                        kind:     EntryKind::Span,
                        label:    $mark->label,
                        tNs:      $mark->tNs,
                        memBytes: $mark->memBytes,
                        depth:    $depth,
                    );
                    $idx = array_key_last($entries);
                    $openIdx[]        = $idx;
                    $childTime[$idx]  = 0;
                    $depth++;
                    break;

                case MarkType::End:
                    if ($openIdx === []) {
                        $entries[] = new Entry(
                            kind:     EntryKind::Point,
                            label:    '[orphan end] ' . $mark->label,
                            tNs:      $mark->tNs,
                            memBytes: $mark->memBytes,
                            depth:    $depth,
                        );
                        break;
                    }

                    $idx      = array_pop($openIdx);
                    $open     = $entries[$idx];
                    $duration = $mark->tNs - $open->tNs;
                    $selfNs   = $duration - ($childTime[$idx] ?? 0);
                    unset($childTime[$idx]);

                    // Credit this span's inclusive duration to its parent's
                    // child-time accumulator so the parent's self-time is
                    // correct when it later closes.
                    if ($openIdx !== []) {
                        $parentIdx = $openIdx[count($openIdx) - 1];
                        $childTime[$parentIdx] = ($childTime[$parentIdx] ?? 0) + $duration;
                    }

                    $label = $open->label === $mark->label
                        ? $open->label
                        : $open->label . ' [mismatched end: ' . $mark->label . ']';

                    $entries[$idx] = new Entry(
                        kind:          $open->kind,
                        label:         $label,
                        tNs:           $open->tNs,
                        memBytes:      $open->memBytes,
                        depth:         $open->depth,
                        durationNs:    $duration,
                        selfNs:        $selfNs,
                        memDeltaBytes: $mark->memBytes - $open->memBytes,
                    );

                    $depth = $open->depth;
                    break;
            }
        }

        return new self($startNs, $endNs, $peakMemoryBytes, $entries);
    }

    public function totalDurationNs(): int
    {
        return $this->endNs - $this->startNs;
    }
}
