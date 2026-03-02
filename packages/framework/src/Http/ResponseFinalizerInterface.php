<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Contract for post-handler response transformation.
 *
 * Finalizers run after a handler returns and can add/normalize transport concerns such
 * as security or cache headers without mutating handler code.
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



