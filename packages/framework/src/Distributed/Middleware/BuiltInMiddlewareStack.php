<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Middleware;

use Celeris\Framework\Distributed\Auth\ServiceAuthMiddleware;
use Celeris\Framework\Distributed\Auth\ServiceAuthenticator;
use Celeris\Framework\Distributed\Observability\ObservabilityDispatcher;
use Celeris\Framework\Distributed\Tracing\TracePropagatorInterface;
use Celeris\Framework\Distributed\Tracing\TracerInterface;
use Celeris\Framework\Middleware\MiddlewareInterface;
use Celeris\Framework\Middleware\Pipeline;

/**
 * Purpose: implement built in middleware stack behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when built in middleware stack functionality is required.
 */
final class BuiltInMiddlewareStack
{
   /** @var array<int, MiddlewareInterface> */
   private array $middleware;

   /**
    * @param array<int, MiddlewareInterface> $middleware
    */
   public function __construct(array $middleware)
   {
      $this->middleware = array_values($middleware);
   }

   /**
    * Handle for service.
    *
    * @param string $serviceName
    * @param ServiceAuthenticator $authenticator
    * @param TracerInterface $tracer
    * @param TracePropagatorInterface $propagator
    * @param ObservabilityDispatcher $observability
    * @param bool $requireServiceAuth
    * @return self
    */
   public static function forService(
      string $serviceName,
      ServiceAuthenticator $authenticator,
      TracerInterface $tracer,
      TracePropagatorInterface $propagator,
      ObservabilityDispatcher $observability,
      bool $requireServiceAuth = true,
   ): self {
      return new self([
         new RequestIdMiddleware(),
         new DistributedTracingMiddleware($serviceName, $tracer, $propagator),
         new ObservabilityMiddleware($serviceName, $observability),
         new ServiceAuthMiddleware($authenticator, $requireServiceAuth),
      ]);
   }

   /**
    * Handle apply to.
    *
    * @param Pipeline $pipeline
    * @return void
    */
   public function applyTo(Pipeline $pipeline): void
   {
      foreach ($this->middleware as $layer) {
         $pipeline->add($layer);
      }
   }

   /**
    * @return array<int, MiddlewareInterface>
    */
   public function all(): array
   {
      return $this->middleware;
   }
}



