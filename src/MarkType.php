<?php

declare(strict_types=1);

namespace Componenta\Profiler;

enum MarkType: string
{
    case Point = 'point';
    case Begin = 'begin';
    case End   = 'end';
}
