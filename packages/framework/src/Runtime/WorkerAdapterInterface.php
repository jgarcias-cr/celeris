<?php

declare(strict_types=1);

namespace Celeris\Framework\Runtime;

use Celeris\Framework\Http\Response;

/**
 * Define the contract for worker adapter interface behavior in the Runtime subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface WorkerAdapterInterface
{
   /** Called once at process start to initialize the runtime adapter */
   public function start(): void;

   /** Wait for the next request frame; returns null when the runtime is shutting down */
   public function nextRequest(): ?RuntimeRequest;

   /** Emit response to the underlying runtime transport */
   public function send(RuntimeRequest $request, Response $response): void;

   /** Perform deterministic cleanup between requests (worker runtimes) */
   public function reset(): void;

   /** Stop the adapter and release resources */
   public function stop(): void;
}


