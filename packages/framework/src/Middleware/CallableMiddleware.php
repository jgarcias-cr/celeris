<?php

declare(strict_types=1);

namespace Celeris\Framework\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Closure;

/**
 * Enforce callable middleware concerns around request handling.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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




