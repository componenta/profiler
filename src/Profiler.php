<?php

declare(strict_types=1);

namespace Componenta\Profiler;

/**
 * Default {@see ProfilerInterface} implementation: flat list of {@see Mark}s
 * appended in call order. Nesting is encoded by `begin` / `end` marks and
 * reconstructed at render time - this keeps the collector lock-free and
 * cheap on the hot path.
 */
final class Profiler implements ProfilerInterface
{
    /** @var list<Mark> */
    private array $marks = [];

    public function mark(string $label): void
    {
        $this->marks[] = Mark::now($label);
    }

    public function span(string $name): Span
    {
        $this->marks[] = Mark::now($name, MarkType::Begin);

        return new Span(function () use ($name): void {
            $this->marks[] = Mark::now($name, MarkType::End);
        });
    }

    public function importMarks(iterable $marks): void
    {
        foreach ($marks as $mark) {
            $this->marks[] = $mark;
        }
    }

    public function marks(): array
    {
        return $this->marks;
    }

    public function startNs(): int
    {
        return $this->marks[0]->tNs ?? 0;
    }

    public function peakMemoryBytes(): int
    {
        return memory_get_peak_usage();
    }
}
