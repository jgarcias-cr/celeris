<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Purpose: define the contract for response finalizer interface behavior in the Http subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete http services and resolved via dependency injection.
 */
interface ResponseFinalizerInterface
{
   /**
    * Handle finalize.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @param Response $response
    * @return Response
    */
   public function finalize(RequestContext $ctx, Request $request, Response $response): Response;
}




