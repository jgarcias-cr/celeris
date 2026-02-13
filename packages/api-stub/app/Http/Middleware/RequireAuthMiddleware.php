<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;

/**
 * Authentication middleware hook for protected API routes.
 *
 * The stub keeps this middleware pass-through for quick startup.
 * Replace with token/session checks and authorization rules before
 * allowing access to sensitive endpoints.
 */
final class RequireAuthMiddleware implements MiddlewareInterface
{
   public function handle(RequestContext $ctx, Request $request, callable $next): Response
   {
      return $next($ctx, $request);
   }
}
