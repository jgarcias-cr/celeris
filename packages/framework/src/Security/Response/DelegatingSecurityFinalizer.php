<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Response;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\ResponseFinalizerInterface;

/**
 * Purpose: implement delegating security finalizer behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when delegating security finalizer functionality is required.
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



