<?php

declare(strict_types=1);

namespace Celeris\Framework\Runtime;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;

/**
 * Purpose: implement runtime request behavior for the Runtime subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by runtime components when runtime request functionality is required.
 */
final class RuntimeRequest
{
   /**
    * Create a new instance.
    *
    * @param RequestContext $context
    * @param Request $request
    * @param mixed $transport
    * @return mixed
    */
   public function __construct(
      private RequestContext $context,
      private Request $request,
      private mixed $transport = null,
   ) {}

   /**
    * Get the context.
    *
    * @return RequestContext
    */
   public function getContext(): RequestContext
   {
      return $this->context;
   }

   /**
    * Set the context.
    *
    * @param RequestContext $context
    * @return void
    */
   public function setContext(RequestContext $context): void
   {
      $this->context = $context;
   }

   /**
    * Get the request.
    *
    * @return Request
    */
   public function getRequest(): Request
   {
      return $this->request;
   }

   /**
    * Get the transport.
    *
    * @return mixed
    */
   public function getTransport(): mixed
   {
      return $this->transport;
   }
}




