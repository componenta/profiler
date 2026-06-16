<?php

declare(strict_types=1);

namespace Componenta\Profiler\Renderer;

use Componenta\Profiler\Timeline\Timeline;
use Psr\Http\Message\ResponseInterface;

/**
 * Pipes the response through each delegate renderer in order. Useful for
 * combining a body-replacing renderer with a header-adding one (e.g.
 * {@see WaterfallRenderer} + {@see ServerTimingRenderer}).
 */
final readonly class CompositeRenderer implements RendererInterface
{
    /** @var list<RendererInterface> */
    private array $renderers;

    public function __construct(RendererInterface ...$renderers)
    {
        $this->renderers = array_values($renderers);
    }

    public function render(Timeline $timeline, ResponseInterface $response): ResponseInterface
    {
        foreach ($this->renderers as $renderer) {
            $response = $renderer->render($timeline, $response);
        }

        return $response;
    }
}
