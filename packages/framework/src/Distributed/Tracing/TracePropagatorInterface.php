<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;

/**
 * Purpose: define the contract for trace propagator interface behavior in the Distributed subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete distributed services and resolved via dependency injection.
 */
interface TracePropagatorInterface
{
   /**
    * Handle extract.
    *
    * @param Request $request
    * @param string $service
    * @return ?TraceContext
    */
   public function extract(Request $request, string $service): ?TraceContext;

   /**
    * Handle inject request.
    *
    * @param Request $request
    * @param TraceContext $context
    * @return Request
    */
   public function injectRequest(Request $request, TraceContext $context): Request;

   /**
    * Handle inject response.
    *
    * @param Response $response
    * @param TraceContext $context
    * @return Response
    */
   public function injectResponse(Response $response, TraceContext $context): Response;
}



