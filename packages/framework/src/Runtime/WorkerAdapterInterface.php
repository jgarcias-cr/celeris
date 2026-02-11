<?php

declare(strict_types=1);

namespace Celeris\Framework\Runtime;

use Celeris\Framework\Http\Response;

/**
 * Purpose: define the contract for worker adapter interface behavior in the Runtime subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete runtime services and resolved via dependency injection.
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


