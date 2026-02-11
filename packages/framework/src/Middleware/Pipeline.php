<?php

declare(strict_types=1);

namespace Celeris\Framework\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\RequestContext;

/**
 * Purpose: implement pipeline behavior for the Middleware subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by middleware components when pipeline functionality is required.
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



