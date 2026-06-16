<?php

declare(strict_types=1);

namespace Componenta\Profiler\Renderer;

use Componenta\Profiler\Timeline\Entry;
use Componenta\Profiler\Timeline\Timeline;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Replaces the body with a JSON dump of the timeline, shaped for machine
 * consumption (CI perf regression checks, offline diffing).
 */
final readonly class JsonRenderer implements RendererInterface
{
    public function __construct(
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function render(Timeline $timeline, ResponseInterface $response): ResponseInterface
    {
        $payload = [
            'totalDurationNs' => $timeline->totalDurationNs(),
            'peakMemoryBytes' => $timeline->peakMemoryBytes,
            'status'          => $response->getStatusCode(),
            'entries'         => array_map($this->entryToArray(...), $timeline->entries),
        ];

        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('X-Profile', 'json')
            ->withBody($this->streamFactory->createStream($body));
    }

    /**
     * @return array{kind: string, label: string, tNs: int, memBytes: int, depth: int, durationNs: ?int, selfNs: ?int, memDeltaBytes: ?int}
     */
    private function entryToArray(Entry $entry): array
    {
        return [
            'kind'          => $entry->kind->value,
            'label'         => $entry->label,
            'tNs'           => $entry->tNs,
            'memBytes'      => $entry->memBytes,
            'depth'         => $entry->depth,
            'durationNs'    => $entry->durationNs,
            'selfNs'        => $entry->selfNs,
            'memDeltaBytes' => $entry->memDeltaBytes,
        ];
    }
}
