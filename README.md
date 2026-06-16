# Componenta Profiler

Development-time profiler for request lifecycle, boot timing, service instrumentation, and timeline rendering.

Use this package as an optional development dependency. It is designed to be easy to disable in production: application code can depend on `ProfilerInterface`, while config decides whether the real profiler or a no-op integration is wired.

## Installation

```bash
composer require componenta/profiler
```

This package intentionally does not declare `extra.componenta.config-providers`.
Register `Componenta\Profiler\ConfigProvider` manually from development-only application config when profiling is enabled.

## Requirements

- PHP 8.4+

## Related Packages

| Package | Why it matters here |
|---|---|
| `psr/http-message` / `psr/http-server-middleware` | Timing and profiler middleware work with PSR-7 requests, PSR-7 responses, and PSR-15 handlers. |
| `componenta/pipeline` | Profiling can wrap middleware pipelines. |
| `componenta/app` | Boot timing and early marks belong to the application lifecycle. |
| `componenta/di` | Service delegators can profile selected classes. |

## Core API

```php
use Componenta\Profiler\Profiler;

$profiler = new Profiler();

$profiler->mark('boot');

$span = $profiler->span('controller');
// run work
$span->close();

$marks = $profiler->marks();
```

Marks are ordered records with labels, timestamps, memory usage, and type:

- `Point`: instant marker
- `Begin`: span start
- `End`: span end

## Early Bootstrap Marks

`Marks` is a process-global facade for points that happen before the DI container exists:

```php
use Componenta\Profiler\Marks;

Marks::mark('autoload.loaded');
```

The application integration can merge these early marks into the configured `ProfilerInterface`.

## Rendering

Renderers convert collected marks into formats useful for debugging:

- `JsonRenderer`: structured machine-readable output.
- `ServerTimingRenderer`: `Server-Timing` header value.
- `WaterfallRenderer`: human-readable timeline.
- `CompositeRenderer`: chooses among renderers.

## HTTP And Framework Integration

The integration layer includes:

- `TimingMiddleware`: marks PSR-15 request handling.
- `ProfilerMiddleware`: exposes profiler output when a trigger matches.
- `TimingBootloader`: records bootloader spans.
- service delegators for profiling selected classes.
- `ProfilingPipelineFactory` for wrapping middleware pipelines.

## Triggers

Triggers decide whether profiling output should be enabled for a request:

- query parameter trigger
- header trigger
- cookie trigger
- composite trigger

This keeps profiling opt-in and avoids exposing profiler data by default.

## DI Registration

`ConfigProvider` registers profiler services, renderers, triggers, and optional integration points. Keep the package dev-only unless the application has an explicit production observability use case.
