<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Ordered pipeline that applies response finalizers.
 *
 * The kernel uses this after handler execution so cross-cutting response behavior stays
 * centralized and deterministic.
 */
final class ResponsePipeline
{
   /** @var array<int, ResponseFinalizerInterface> */
   private array $finalizers = [];

   /**
    * Handle add.
    *
    * @param ResponseFinalizerInterface $finalizer
    * @return void
    */
   public function add(ResponseFinalizerInterface $finalizer): void
   {
      $this->finalizers[] = $finalizer;
   }

   /**
    * Handle handle.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @param Response $response
    * @return Response
    */
   public function handle(RequestContext $ctx, Request $request, Response $response): Response
   {
      foreach ($this->finalizers as $finalizer) {
         $response = $finalizer->finalize($ctx, $request, $response);
      }

      return $response;
   }
}



