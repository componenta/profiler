<?php

declare(strict_types=1);

namespace Componenta\Profiler;

use Closure;

/**
 * RAII handle for a measured span.
 *
 * A `begin` mark is emitted when the handle is created (inside
 * {@see ProfilerInterface::span()} or {@see Marks::span()}); the matching
 * `end` mark is emitted by the `onClose` callback on explicit
 * {@see close()} or when PHP refcounts the handle down to zero.
 *
 * ```
 * $s = $profiler->span('db.select');
 * $rows = $db->query(...);      // measured
 * unset($s);                    // or drop out of scope
 * ```
 *
 * Idempotent: `close()` on an already-closed span is a no-op.
 */
final class Span
{
    private bool $closed = false;

    /**
     * @internal Spans are produced by {@see ProfilerInterface::span()}.
     *
     * @param Closure(): void $onClose
     */
    public function __construct(
        private readonly Closure $onClose,
    ) {}

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        ($this->onClose)();
    }

    public function __destruct()
    {
        $this->close();
    }
}
