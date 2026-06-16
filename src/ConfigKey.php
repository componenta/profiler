<?php

declare(strict_types=1);

namespace Componenta\Profiler;

/**
 * Config keys for the profiler package. All settings live under the
 * `profiler` root key so merging with other package configs is predictable.
 */
final class ConfigKey
{
    public const string ROOT = 'profiler';

    /** Directory used by {@see Integration\ClassProxyGenerator} for generated proxy files. */
    public const string PROXY_CACHE_DIR = 'proxy_cache_dir';

    /**
     * List of class-strings to be wrapped in profiling proxies via DI
     * delegator. App-level dev config provider contributes the concrete
     * classes it wants measured.
     *
     * @see Integration\ClassProfilerDelegator
     */
    public const string PROFILED_CLASSES = 'profiled_classes';

    /** List of bootloader class-strings whose `boot()` calls should be timed. */
    public const string PROFILED_BOOTLOADERS = 'profiled_bootloaders';
}
