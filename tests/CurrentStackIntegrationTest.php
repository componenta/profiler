<?php

declare(strict_types=1);

namespace Componenta\Profiler\Tests;

use Componenta\Config\Config;
use Componenta\Config\ConfigKey as ConfigDependencyKey;
use Componenta\DI\Container;
use Componenta\Http\Middleware\PipelineFactory;
use Componenta\Http\Middleware\PipelineFactoryInterface;
use Componenta\Profiler\ConfigProvider;
use Componenta\Profiler\Integration\BootloaderTimingDelegator;
use Componenta\Profiler\Integration\ClassProfilerDelegator;
use Componenta\Profiler\Integration\ProfilingPipelineFactory;
use Componenta\Stdlib\PathResolver;
use Componenta\Stdlib\PathResolverInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;

final class CurrentStackIntegrationTest extends TestCase
{
    public function testProviderBuildsWithCurrentConfigAndDi(): void
    {
        $configuration = (new ConfigProvider())();
        $dependencies = $configuration[ConfigDependencyKey::DEPENDENCIES] ?? [];
        $dependencies[ConfigDependencyKey::INVOKABLES][] = PipelineFactory::class;
        $dependencies[ConfigDependencyKey::ALIASES][PipelineFactoryInterface::class] = PipelineFactory::class;
        $dependencies[ConfigDependencyKey::SERVICES][PathResolverInterface::class] = new PathResolver(sys_get_temp_dir());
        $dependencies[ConfigDependencyKey::SERVICES][StreamFactoryInterface::class] = new Psr17Factory();
        $configuration[ConfigDependencyKey::DEPENDENCIES] = $dependencies;

        $container = Container::create(new Config($configuration));

        self::assertInstanceOf(
            ProfilingPipelineFactory::class,
            $container->get(PipelineFactoryInterface::class),
        );
        self::assertInstanceOf(
            BootloaderTimingDelegator::class,
            $container->get(BootloaderTimingDelegator::class),
        );
        self::assertInstanceOf(
            ClassProfilerDelegator::class,
            $container->get(ClassProfilerDelegator::class),
        );
    }
}
