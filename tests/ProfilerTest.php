<?php

declare(strict_types=1);

namespace Componenta\Profiler\Tests;

use Componenta\Profiler\MarkType;
use Componenta\Profiler\Profiler;
use PHPUnit\Framework\TestCase;

final class ProfilerTest extends TestCase
{
    public function testCollectsMarksAndSpans(): void
    {
        $profiler = new Profiler();

        $profiler->mark('boot');
        $span = $profiler->span('handler');
        $span->close();

        $marks = $profiler->marks();

        self::assertCount(3, $marks);
        self::assertSame('boot', $marks[0]->label);
        self::assertSame(MarkType::Point, $marks[0]->type);
        self::assertSame(MarkType::Begin, $marks[1]->type);
        self::assertSame(MarkType::End, $marks[2]->type);
        self::assertGreaterThan(0, $profiler->startNs());
        self::assertGreaterThan(0, $profiler->peakMemoryBytes());
    }
}
