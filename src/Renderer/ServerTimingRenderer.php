<?php

declare(strict_types=1);

namespace Componenta\Profiler\Renderer;

use Componenta\Profiler\Timeline\EntryKind;
use Componenta\Profiler\Timeline\Timeline;
use Psr\Http\Message\ResponseInterface;

/**
 * Appends a `Server-Timing` header (W3C) to the response without touching
 * the body.
 *
 * Emits every span from the timeline using its **self** duration, not the
 * inclusive one. Exclusive time is what's actionable in DevTools: an outer
 * PSR-15 middleware appears with its 0.02 ms of own work rather than the
 * 59 ms it spent waiting on downstream stages. Points are skipped - they
 * contribute nothing a DevTools timeline can render.
 *
 * Output order follows the timeline; `maxEntries` caps the header length so
 * browsers don't truncate mid-entry.
 */
final readonly class ServerTimingRenderer implements RendererInterface
{
    public function __construct(
        private int $maxEntries = 32,
    ) {}

    public function render(Timeline $timeline, ResponseInterface $response): ResponseInterface
    {
        $parts = $this->buildParts($timeline);

        if ($parts === []) {
            return $response;
        }

        return $response->withHeader('Server-Timing', implode(', ', $parts));
    }

    /**
     * @return list<string>
     */
    private function buildParts(Timeline $timeline): array
    {
        $parts = [];
        $used  = [];

        foreach ($timeline->entries as $entry) {
            if ($entry->kind !== EntryKind::Span) {
                continue;
            }

            if (count($parts) >= $this->maxEntries) {
                break;
            }

            $selfMs = $entry->selfNs !== null
                ? $entry->selfNs / 1_000_000
                : null;

            $name = $this->sanitizeName($entry->label, $used);

            $parts[] = $selfMs === null
                ? $name
                : sprintf('%s;dur=%.2f', $name, $selfMs);
        }

        return $parts;
    }

    /**
     * RFC 8941-ish token: ASCII letters, digits, and `._-` only. Unique per
     * header so browsers don't collapse repeated names.
     *
     * @param array<string, int> $used  Accumulator of used names + counters.
     */
    private function sanitizeName(string $label, array &$used): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $label) ?? 'entry';
        $base = trim($base, '_');

        if ($base === '') {
            $base = 'entry';
        }

        $used[$base] = ($used[$base] ?? 0) + 1;

        return $used[$base] === 1
            ? $base
            : $base . '_' . $used[$base];
    }
}
