<?php

declare(strict_types=1);

namespace Componenta\Profiler;

use Componenta\Profiler\Integration\BootloaderTimingDelegator;
use Componenta\Profiler\Integration\ClassProfilerDelegator;

/**
 * Convenience helpers for building the `delegators` section of a dev-only
 * config provider. The returned arrays are meant to be spread into
 * `ConfigKey::DEPENDENCIES => [ConfigKey::DELEGATORS => [...]]`:
 *
 * ```
 * use function Componenta\Profiler\profile_classes;
 * use function Componenta\Profiler\time_bootloaders;
 *
 * return [
 *     'dependencies' => [
 *         'delegators' => [
 *             ...time_bootloaders(HttpBootloader::class),
 *             ...profile_classes(PostRepository::class),
 *         ],
 *     ],
 * ];
 * ```
 */

/**
 * @param class-string ...$classes
 *
 * @return array<class-string, array{class-string}>
 */
function profile_classes(string ...$classes): array
{
    return array_fill_keys($classes, [ClassProfilerDelegator::class]);
}

/**
 * @param class-string ...$bootloaders
 *
 * @return array<class-string, array{class-string}>
 */
function time_bootloaders(string ...$bootloaders): array
{
    return array_fill_keys($bootloaders, [BootloaderTimingDelegator::class]);
}
