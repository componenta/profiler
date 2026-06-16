<?php

declare(strict_types=1);

namespace Componenta\Profiler\Timeline;

enum EntryKind: string
{
    case Point = 'point';
    case Span  = 'span';
}
