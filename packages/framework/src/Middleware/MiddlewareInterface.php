<?php

declare(strict_types=1);

namespace Celeris\Framework\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\RequestContext;

/**
 * Define the contract for middleware interface behavior in the Middleware subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



