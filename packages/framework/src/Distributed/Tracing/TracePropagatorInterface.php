<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;

/**
 * Define the contract for trace propagator interface behavior in the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



