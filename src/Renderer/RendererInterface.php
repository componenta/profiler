<?php

declare(strict_types=1);

namespace Componenta\Profiler\Renderer;

use Componenta\Profiler\Timeline\Timeline;
use Psr\Http\Message\ResponseInterface;

/**
 * Transforms a {@see Timeline} into an HTTP response representation.
 *
 * Renderers receive the original response so they can either replace the
 * body (waterfall/json) or piggy-back metadata on it (Server-Timing header
 * that leaves the payload untouched). They must return a PSR-7 response -
 * `RendererInterface` is not responsible for emission.
 */
interface RendererInterface
{
    public function render(Timeline $timeline, ResponseInterface $response): ResponseInterface;
}
