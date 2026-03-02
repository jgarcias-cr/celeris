<?php

declare(strict_types=1);

namespace Celeris\Framework\Runtime;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use InvalidArgumentException;

/**
 * Bridge framework contracts with an external/runtime swoole adapter integration.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class SwooleAdapter implements WorkerAdapterInterface
{
   /** @var callable(): (RuntimeRequest|array<string, mixed>|null) */
   private $receiver;
   /** @var callable(RuntimeRequest, Response): void */
   private $responder;
   /** @var callable(): void|null */
   private $starter;
   /** @var callable(): void|null */
   private $resetter;
   /** @var callable(): void|null */
   private $stopper;

   /**
    * @param callable(): (RuntimeRequest|array<string, mixed>|null) $receiver
    * @param callable(RuntimeRequest, Response): void $responder
    * @param callable(): void|null $starter
    * @param callable(): void|null $resetter
    * @param callable(): void|null $stopper
    */
   public function __construct(
      callable $receiver,
      callable $responder,
      ?callable $starter = null,
      ?callable $resetter = null,
      ?callable $stopper = null
   ) {
      $this->receiver = $receiver;
      $this->responder = $responder;
      $this->starter = $starter;
      $this->resetter = $resetter;
      $this->stopper = $stopper;
   }

   /**
    * Handle start.
    *
    * @return void
    */
   public function start(): void
   {
      if ($this->starter !== null) {
         ($this->starter)();
      }
   }

   /**
    * Handle next request.
    *
    * @return ?RuntimeRequest
    */
   public function nextRequest(): ?RuntimeRequest
   {
      $frame = ($this->receiver)();
      if ($frame === null) {
         return null;
      }

      return self::normalizeFrame($frame);
   }

   /**
    * Handle send.
    *
    * @param RuntimeRequest $request
    * @param Response $response
    * @return void
    */
   public function send(RuntimeRequest $request, Response $response): void
   {
      ($this->responder)($request, $response);
   }

   /**
    * Handle reset.
    *
    * @return void
    */
   public function reset(): void
   {
      if ($this->resetter !== null) {
         ($this->resetter)();
      }
   }

   /**
    * Handle stop.
    *
    * @return void
    */
   public function stop(): void
   {
      if ($this->stopper !== null) {
         ($this->stopper)();
      }
   }

   /**
    * @param RuntimeRequest|array<string, mixed> $frame
    */
   private static function normalizeFrame(RuntimeRequest|array $frame): RuntimeRequest
   {
      if ($frame instanceof RuntimeRequest) {
         return $frame;
      }

      $context = $frame['context'] ?? null;
      $request = $frame['request'] ?? null;
      $transport = $frame['transport'] ?? null;

      if (!$context instanceof RequestContext || !$request instanceof Request) {
         throw new InvalidArgumentException(
            'Swoole receiver must return RuntimeRequest or array with RequestContext + Request.'
         );
      }

      return new RuntimeRequest($context, $request, $transport);
   }
}




