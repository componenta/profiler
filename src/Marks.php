<?php

declare(strict_types=1);

namespace Componenta\Profiler;

/**
 * Process-global facade for ad-hoc instrumentation.
 *
 * Intended for two cases:
 *
 *  1. **Bootstrap-phase marks.** Before the DI container is built and the
 *     profiler instance exists, callers can still `Marks::stamp('boot.autoload')`.
 *     Entries are buffered and replayed into the profiler by {@see bind()}.
 *
 *  2. **Temporary diagnostic marks** inside application code that the
 *     developer will remove before committing. Because the profiler package
 *     is `require-dev`, calls left in committed code will fatal in
 *     production - that is intentional: this API is a scalpel, not a
 *     permanent instrumentation surface. For persistent per-class timing,
 *     register the class for auto-instrumentation in the dev config
 *     provider instead.
 *
 * Static state is tolerated here because it sits at a boundary the app
 * touches once per request; no other part of the profiler relies on
 * singletons.
 */
final class Marks
{
    private static ?ProfilerInterface $profiler = null;

    /** @var list<Mark> */
    private static array $pending = [];

    /**
     * Bind the profiler instance and drain any marks collected before binding.
     */
    public static function bind(ProfilerInterface $profiler): void
    {
        self::$profiler = $profiler;

        if (self::$pending !== []) {
            $profiler->importMarks(self::$pending);
            self::$pending = [];
        }
    }

    /**
     * Forget the bound profiler. Intended for tests and long-running workers
     * that process multiple requests.
     */
    public static function reset(): void
    {
        self::$profiler = null;
        self::$pending  = [];
    }

    public static function stamp(string $label): void
    {
        if (self::$profiler !== null) {
            self::$profiler->mark($label);

            return;
        }

        self::$pending[] = Mark::now($label);
    }

    public static function span(string $name): Span
    {
        if (self::$profiler !== null) {
            return self::$profiler->span($name);
        }

        self::$pending[] = Mark::now($name, MarkType::Begin);

        return new Span(static function () use ($name): void {
            // Close routes through the facade so that if a profiler has been
            // bound between `span()` and close, the `end` mark lands on it
            // instead of the pending buffer.
            $end = Mark::now($name, MarkType::End);

            if (self::$profiler !== null) {
                self::$profiler->importMarks([$end]);

                return;
            }

            self::$pending[] = $end;
        });
    }
}
