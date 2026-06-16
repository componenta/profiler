<?php

declare(strict_types=1);

namespace Componenta\Profiler\Renderer;

use Componenta\Profiler\Timeline\Entry;
use Componenta\Profiler\Timeline\EntryKind;
use Componenta\Profiler\Timeline\Timeline;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Replaces the response body with a human-readable text waterfall.
 *
 * Each entry renders on its own line with cumulative offset, self-duration
 * (span width), memory, and depth-based indentation. Points render at zero
 * width.
 */
final readonly class WaterfallRenderer implements RendererInterface
{
    public function __construct(
        private StreamFactoryInterface $streamFactory,
        private int                    $indentWidth = 2,
    ) {}

    public function render(Timeline $timeline, ResponseInterface $response): ResponseInterface
    {
        $body = $this->renderBody($timeline, $response);
        $stream = $this->streamFactory->createStream($body);

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('X-Profile', 'waterfall')
            ->withBody($stream);
    }

    private function renderBody(Timeline $timeline, ResponseInterface $response): string
    {
        $out = sprintf(
            "HTTP %d  entries=%d  total=%.2f ms  peakMem=%.2f MB\n\n",
            $response->getStatusCode(),
            count($timeline->entries),
            $timeline->totalDurationNs() / 1_000_000,
            $timeline->peakMemoryBytes / (1024 * 1024),
        );

        if ($timeline->entries === []) {
            return $out . "(no entries recorded)\n";
        }

        $out .= "=== timeline ===\n";

        $start = $timeline->startNs;
        $prev  = $start;
        $labelWidth = $this->longestLabelWidth($timeline->entries);

        foreach ($timeline->entries as $entry) {
            $offsetMs = ($entry->tNs - $start) / 1_000_000;
            $sincePrev = ($entry->tNs - $prev) / 1_000_000;
            $prev = $entry->tNs;

            $indent = str_repeat(' ', $entry->depth * $this->indentWidth);
            $label  = $indent . $this->formatLabel($entry);
            $padded = str_pad($label, $labelWidth + $entry->depth * $this->indentWidth);

            if ($entry->kind === EntryKind::Span) {
                $durMs = $entry->durationNs !== null
                    ? sprintf('%7.2f ms', $entry->durationNs / 1_000_000)
                    : '   ??.?? ms';
                $selfMs = $entry->selfNs !== null
                    ? sprintf('%7.2f ms', $entry->selfNs / 1_000_000)
                    : '   ??.?? ms';
                $memDelta = $this->formatMemDelta($entry->memDeltaBytes);
                $out .= sprintf(
                    "  %s  +%7.2f ms  dur=%s  self=%s  mem=%s\n",
                    $padded,
                    $offsetMs,
                    $durMs,
                    $selfMs,
                    $memDelta,
                );

                continue;
            }

            $out .= sprintf(
                "  %s  +%7.2f ms  Δ%+7.2f ms\n",
                $padded,
                $offsetMs,
                $sincePrev,
            );
        }

        return $out;
    }

    private function formatLabel(Entry $entry): string
    {
        return $entry->kind === EntryKind::Span
            ? '▸ ' . $entry->label
            : '- ' . $entry->label;
    }

    private function formatMemDelta(?int $bytes): string
    {
        if ($bytes === null) {
            return '    ??';
        }

        $sign = $bytes >= 0 ? '+' : '-';
        $abs  = abs($bytes);

        return match (true) {
            $abs >= 1024 * 1024 => sprintf('%s%6.2f MB', $sign, $abs / (1024 * 1024)),
            $abs >= 1024        => sprintf('%s%6.2f KB', $sign, $abs / 1024),
            default             => sprintf('%s%6d  B', $sign, $abs),
        };
    }

    /**
     * @param list<Entry> $entries
     */
    private function longestLabelWidth(array $entries): int
    {
        $max = 0;

        foreach ($entries as $entry) {
            $len = strlen($this->formatLabel($entry));

            if ($len > $max) {
                $max = $len;
            }
        }

        return $max;
    }
}
