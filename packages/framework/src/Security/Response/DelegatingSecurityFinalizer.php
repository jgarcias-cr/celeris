<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Response;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\ResponseFinalizerInterface;

/**
 * Implement delegating security finalizer behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class DelegatingSecurityFinalizer implements ResponseFinalizerInterface
{
   /** @var callable(): SecurityHeadersFinalizer */
   private $resolver;

   /**
    * @param callable(): SecurityHeadersFinalizer $resolver
    */
   public function __construct(callable $resolver)
   {
      $this->resolver = $resolver;
   }

   /**
    * Handle finalize.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @param Response $response
    * @return Response
    */
   public function finalize(RequestContext $ctx, Request $request, Response $response): Response
   {
      $finalizer = ($this->resolver)();
      return $finalizer->finalize($ctx, $request, $response);
   }
}



