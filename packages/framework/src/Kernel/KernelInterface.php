<?php

declare(strict_types=1);

namespace Celeris\Framework\Kernel;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\RequestContext;

/**
 * Purpose: define the contract for kernel interface behavior in the Kernel subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete kernel services and resolved via dependency injection.
 */
interface KernelInterface
{
   /**
    * Handle boot.
    *
    * @return void
    */
   public function boot(): void;

   /**
    * Handle handle.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   public function handle(RequestContext $ctx, Request $request): Response;

   /**
    * Handle reset.
    *
    * @return void
    */
   public function reset(): void;

   /**
    * Handle on request cleanup.
    *
    * @param callable $hook
    * @return void
    */
   public function onRequestCleanup(callable $hook): void;

   /**
    * Handle on shutdown.
    *
    * @param callable $hook
    * @return void
    */
   public function onShutdown(callable $hook): void;

   /**
    * Handle shutdown.
    *
    * @return void
    */
   public function shutdown(): void;
}



