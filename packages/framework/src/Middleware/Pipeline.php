<?php

declare(strict_types=1);

namespace Celeris\Framework\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\RequestContext;

/**
 * Implement pipeline behavior for the Middleware subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Pipeline
{
   /** @var MiddlewareInterface[] */
   private array $stack = [];

   /**
    * Handle add.
    *
    * @param MiddlewareInterface $m
    * @return void
    */
   public function add(MiddlewareInterface $m): void
   {
      $this->stack[] = $m;
   }

   /**
    * Handle all.
    * @return array<int, MiddlewareInterface>
    */
   public function all(): array
   {
      return $this->stack;
   }

   /**
    * Handle handle.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @param callable $final
    * @return Response
    */
   public function handle(RequestContext $ctx, Request $request, callable $final): Response
   {
      // Copy the stack so each request gets a clean execution chain.
      $middleware = $this->stack;

      $runner = function (RequestContext $c, Request $r) use (&$middleware, $final, &$runner): Response {
         $m = array_shift($middleware);
         if ($m === null) {
            return $final($c, $r);
         }

         return $m->handle($c, $r, function (RequestContext $nc, Request $nr) use ($runner): Response {
            return $runner($nc, $nr);
         });
      };

      return $runner($ctx, $request);
   }
}



