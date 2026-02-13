<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Http\Middleware;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;
use Celeris\Sample\Pulse\Config\PulseSettings;

/**
 * Guards Pulse endpoints by environment and optional access token.
 *
 * Requests are rejected when Pulse is disabled, environment is not
 * allowed, or token authentication fails.
 */
final class PulseAccessMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly PulseSettings $settings,
    ) {
    }

    public function handle(RequestContext $ctx, Request $request, callable $next): Response
    {
        $appEnvironment = strtolower(trim((string) $this->config->get('app.env', 'production')));

        if (!$this->settings->enabled || !$this->settings->isEnvironmentAllowed($appEnvironment)) {
            return $this->notFound();
        }

        if ($this->settings->token !== null) {
            $presentedToken = (string) ($request->getHeader('x-celeris-pulse-token', '') ?? '');
            if (!hash_equals($this->settings->token, $presentedToken)) {
                return new Response(401, ['content-type' => 'text/plain; charset=utf-8'], 'Unauthorized');
            }
        }

        return $next($ctx, $request);
    }

    private function notFound(): Response
    {
        return new Response(404, ['content-type' => 'text/plain; charset=utf-8'], 'Not Found');
    }
}
