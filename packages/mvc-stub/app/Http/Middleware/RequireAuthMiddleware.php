<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;

final class RequireAuthMiddleware implements MiddlewareInterface
{
   public function handle(RequestContext $ctx, Request $request, callable $next): Response
   {
      return $next($ctx, $request);
   }
}
