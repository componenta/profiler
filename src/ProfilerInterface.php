<?php

declare(strict_types=1);

namespace Componenta\Profiler;

interface ProfilerInterface
{
    /**
     * Record an instantaneous event at the current time.
     */
    public function mark(string $label): void;

    /**
     * Open a measured span. Emits a `begin` mark immediately; the returned
     * handle emits the matching `end` mark on close / destruction.
     */
    public function span(string $name): Span;

    /**
     * Inject externally collected marks (e.g. bootstrap-phase marks captured
     * in a local array before the profiler was built). Marks keep their
     * original timestamps.
     *
     * @param iterable<Mark> $marks
     */
    public function importMarks(iterable $marks): void;

    /**
     * @return list<Mark>
     */
    public function marks(): array;

    /**
     * Monotonic timestamp (nanoseconds, `hrtime(true)` scale) of the earliest
     * recorded mark; 0 if no marks have been recorded.
     */
    public function startNs(): int;

    public function peakMemoryBytes(): int;
}
