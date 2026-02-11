<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed;

use Celeris\Framework\Distributed\Auth\ServiceAuthenticator;
use Celeris\Framework\Distributed\Auth\ServiceRequestSigner;
use Celeris\Framework\Distributed\Messaging\InMemoryMessageBus;
use Celeris\Framework\Distributed\Messaging\MessageBusInterface;
use Celeris\Framework\Distributed\Messaging\MessageEnvelope;
use Celeris\Framework\Distributed\Middleware\BuiltInMiddlewareStack;
use Celeris\Framework\Distributed\Observability\ObservabilityDispatcher;
use Celeris\Framework\Distributed\Tracing\NullTracer;
use Celeris\Framework\Distributed\Tracing\TraceContext;
use Celeris\Framework\Distributed\Tracing\TracePropagatorInterface;
use Celeris\Framework\Distributed\Tracing\TracerInterface;
use Celeris\Framework\Distributed\Tracing\W3CTraceContextPropagator;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;
use Celeris\Framework\Middleware\Pipeline;

/**
 * Purpose: implement microservice runtime model behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when microservice runtime model functionality is required.
 */
final class MicroserviceRuntimeModel
{
   private Pipeline $pipeline;
   private MessageBusInterface $messageBus;
   private TracerInterface $tracer;
   private TracePropagatorInterface $propagator;
   private ObservabilityDispatcher $observability;
   private ServiceRequestSigner $requestSigner;

   /**
    * Create a new instance.
    *
    * @param string $serviceName
    * @param string $serviceSecret
    * @param ServiceAuthenticator $inboundAuthenticator
    * @param ?MessageBusInterface $messageBus
    * @param ?TracerInterface $tracer
    * @param ?TracePropagatorInterface $propagator
    * @param ?ObservabilityDispatcher $observability
    * @param bool $requireServiceAuth
    * @param ?Pipeline $pipeline
    * @return mixed
    */
   public function __construct(
      private string $serviceName,
      string $serviceSecret,
      ServiceAuthenticator $inboundAuthenticator,
      ?MessageBusInterface $messageBus = null,
      ?TracerInterface $tracer = null,
      ?TracePropagatorInterface $propagator = null,
      ?ObservabilityDispatcher $observability = null,
      bool $requireServiceAuth = true,
      ?Pipeline $pipeline = null,
   ) {
      $this->pipeline = $pipeline ?? new Pipeline();
      $this->messageBus = $messageBus ?? new InMemoryMessageBus();
      $this->tracer = $tracer ?? new NullTracer();
      $this->propagator = $propagator ?? new W3CTraceContextPropagator();
      $this->observability = $observability ?? new ObservabilityDispatcher();
      $this->requestSigner = new ServiceRequestSigner($this->serviceName, $serviceSecret);

      $stack = BuiltInMiddlewareStack::forService(
         $this->serviceName,
         $inboundAuthenticator,
         $this->tracer,
         $this->propagator,
         $this->observability,
         $requireServiceAuth,
      );
      $stack->applyTo($this->pipeline);
   }

   /**
    * Handle service name.
    *
    * @return string
    */
   public function serviceName(): string
   {
      return $this->serviceName;
   }

   /**
    * Handle pipeline.
    *
    * @return Pipeline
    */
   public function pipeline(): Pipeline
   {
      return $this->pipeline;
   }

   /**
    * Handle message bus.
    *
    * @return MessageBusInterface
    */
   public function messageBus(): MessageBusInterface
   {
      return $this->messageBus;
   }

   /**
    * Handle tracer.
    *
    * @return TracerInterface
    */
   public function tracer(): TracerInterface
   {
      return $this->tracer;
   }

   /**
    * Handle observability.
    *
    * @return ObservabilityDispatcher
    */
   public function observability(): ObservabilityDispatcher
   {
      return $this->observability;
   }

   /**
    * Handle propagator.
    *
    * @return TracePropagatorInterface
    */
   public function propagator(): TracePropagatorInterface
   {
      return $this->propagator;
   }

   /**
    * Handle add middleware.
    *
    * @param MiddlewareInterface $middleware
    * @return void
    */
   public function addMiddleware(MiddlewareInterface $middleware): void
   {
      $this->pipeline->add($middleware);
   }

   /**
    * @param callable(RequestContext, Request): Response $handler
    */
   public function handle(RequestContext $ctx, Request $request, callable $handler): Response
   {
      return $this->pipeline->handle($ctx, $request, $handler);
   }

   /**
    * Handle prepare outbound request.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Request
    */
   public function prepareOutboundRequest(RequestContext $ctx, Request $request): Request
   {
      $baseTrace = $ctx->getAttribute('trace.context');
      if (!$baseTrace instanceof TraceContext) {
         $baseTrace = TraceContext::root($this->serviceName);
      }

      $outboundTrace = $baseTrace->child($this->serviceName);
      $withTrace = $this->propagator->injectRequest($request, $outboundTrace);
      return $this->requestSigner->sign($withTrace);
   }

   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $headers
    */
   public function publishMessage(
      RequestContext $ctx,
      string $topic,
      string $name,
      mixed $payload,
      array $headers = [],
   ): MessageEnvelope {
      $traceContext = $ctx->getAttribute('trace.context');
      if ($traceContext instanceof TraceContext) {
         $headers['traceparent'] = $traceContext->toTraceParent();
         $headers['x-trace-id'] = $traceContext->traceId();
      }

      $headers['service'] = $this->serviceName;
      $envelope = MessageEnvelope::create($topic, $name, $payload, $headers);
      $this->messageBus->publish($envelope);

      $this->observability->emit('message.publish', [
         'service' => $this->serviceName,
         'topic' => $topic,
         'name' => $name,
         'message_id' => $envelope->id(),
      ]);

      return $envelope;
   }
}



