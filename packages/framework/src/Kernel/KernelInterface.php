<?php

declare(strict_types=1);

namespace Celeris\Framework\Kernel;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\RequestContext;

/**
 * Define the contract for kernel interface behavior in the Kernel subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



