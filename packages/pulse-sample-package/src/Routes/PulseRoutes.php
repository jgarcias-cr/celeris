<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Routes;

use Celeris\Framework\Routing\RouteCollector;
use Celeris\Framework\Routing\RouteGroup;
use Celeris\Framework\Routing\RouteMetadata;
use Celeris\Sample\Pulse\Http\Controllers\PulseController;
use Celeris\Sample\Pulse\Http\Middleware\PulseAccessMiddleware;

/**
 * Registers Pulse route endpoints under a configurable prefix.
 *
 * Routes are grouped with access middleware and route metadata suitable
 * for documentation and diagnostics.
 */
final class PulseRoutes
{
    public static function register(RouteCollector $routes, string $prefix = '/_pulse'): void
    {
        $routes->group(
            new RouteGroup(prefix: $prefix, middleware: [PulseAccessMiddleware::class], tags: ['Pulse']),
            static function (RouteCollector $r): void {
                $r->get(
                    '/summary',
                    [PulseController::class, 'summary'],
                    [],
                    new RouteMetadata(
                        name: 'pulse.summary',
                        summary: 'Pulse summary',
                    ),
                );
                $r->get(
                    '/requests/slow',
                    [PulseController::class, 'slowRequests'],
                    [],
                    new RouteMetadata(
                        name: 'pulse.requests.slow',
                        summary: 'Slow requests',
                    ),
                );
                $r->get(
                    '/users/active',
                    [PulseController::class, 'activeUsers'],
                    [],
                    new RouteMetadata(
                        name: 'pulse.users.active',
                        summary: 'Most active users',
                    ),
                );
                $r->get(
                    '/tasks/slow',
                    [PulseController::class, 'slowTasks'],
                    [],
                    new RouteMetadata(
                        name: 'pulse.tasks.slow',
                        summary: 'Slow tasks',
                    ),
                );
            },
        );
    }
}
