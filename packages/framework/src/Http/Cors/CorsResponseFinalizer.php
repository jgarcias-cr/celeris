<?php

declare(strict_types=1);

namespace Celeris\Framework\Http\Cors;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\ResponseFinalizerInterface;

/**
 * Adds CORS headers to eligible responses after handler execution.
 */
final class CorsResponseFinalizer implements ResponseFinalizerInterface
{
   /**
    * Create the CORS response finalizer with an optional explicit policy.
    */
   public function __construct(private ?CorsPolicy $policy = null)
   {
      $this->policy ??= new CorsPolicy();
   }

   /**
    * Apply CORS headers to the outgoing response when the request qualifies.
    */
   public function finalize(RequestContext $ctx, Request $request, Response $response): Response
   {
      $decision = $this->policy->evaluate($ctx, $request);
      if (($decision['preflight'] ?? false) === true) {
         return $response;
      }

      return $this->policy->applyActualHeaders($response, $decision);
   }
}
