<?php

declare(strict_types=1);

namespace Celeris\Framework\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Closure;

/**
 * Purpose: enforce callable middleware concerns around request handling.
 * How: runs before and/or after the next pipeline stage and may short-circuit with a response.
 * Used in framework: registered in request pipelines to apply cross-cutting behavior per request.
 */
final class CallableMiddleware implements MiddlewareInterface
{
   /** @var Closure(RequestContext, Request, callable): Response */
   private Closure $handler;

   /**
    * @param callable(RequestContext, Request, callable): Response $handler
    */
   public function __construct(callable $handler)
   {
      $this->handler = $handler instanceof Closure ? $handler : Closure::fromCallable($handler);
   }

   /**
    * Handle handle.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @param callable $next
    * @return Response
    */
   public function handle(RequestContext $ctx, Request $request, callable $next): Response
   {
      return ($this->handler)($ctx, $request, $next);
   }
}




