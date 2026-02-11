<?php

declare(strict_types=1);

namespace Celeris\Framework\Runtime;

use Celeris\Framework\Kernel\KernelInterface;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Security\SecurityException;
use Throwable;

/**
 * Purpose: implement worker runner behavior for the Runtime subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by runtime components when worker runner functionality is required.
 */
final class WorkerRunner
{
   private KernelInterface $kernel;
   private WorkerAdapterInterface $adapter;

   /**
    * Create a new instance.
    *
    * @param KernelInterface $kernel
    * @param WorkerAdapterInterface $adapter
    * @return mixed
    */
   public function __construct(KernelInterface $kernel, WorkerAdapterInterface $adapter)
   {
      $this->kernel = $kernel;
      $this->adapter = $adapter;
   }

   /**
    * Handle run.
    *
    * @return void
    */
   public function run(): void
   {
      $this->kernel->boot();
      $this->adapter->start();

      try {
         while (true) {
            $runtimeRequest = $this->adapter->nextRequest();
            if ($runtimeRequest === null) {
               break;
            }

            try {
               $response = $this->kernel->handle(
                  $runtimeRequest->getContext(),
                  $runtimeRequest->getRequest(),
               );
            } catch (SecurityException $exception) {
               $response = new Response(
                  $exception->getStatus(),
                  array_merge(['content-type' => 'text/plain; charset=utf-8'], $exception->getHeaders()),
                  $exception->getMessage(),
               );
            } catch (Throwable) {
               $response = new Response(
                  500,
                  ['content-type' => 'text/plain; charset=utf-8'],
                  'Internal Server Error'
               );
            }

            try {
               $this->adapter->send($runtimeRequest, $response);
            } finally {
               $this->kernel->reset();
               $this->adapter->reset();
            }
         }
      } finally {
         $this->adapter->stop();
         $this->kernel->shutdown();
      }
   }
}



