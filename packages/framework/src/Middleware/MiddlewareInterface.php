<?php

declare(strict_types=1);

namespace Celeris\Framework\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\RequestContext;

/**
 * Purpose: define the contract for middleware interface behavior in the Middleware subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete middleware services and resolved via dependency injection.
 */
interface MiddlewareInterface
{
   /**
    * Handle handle.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @param callable $next
    * @return Response
    */
   public function handle(RequestContext $ctx, Request $request, callable $next): Response;
}



