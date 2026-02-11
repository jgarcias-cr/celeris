<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Purpose: implement response pipeline behavior for the Http subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by http components when response pipeline functionality is required.
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




