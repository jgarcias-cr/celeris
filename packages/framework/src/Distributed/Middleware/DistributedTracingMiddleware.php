<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Middleware;

use Celeris\Framework\Distributed\Tracing\TraceContext;
use Celeris\Framework\Distributed\Tracing\TracePropagatorInterface;
use Celeris\Framework\Distributed\Tracing\TracerInterface;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;
use Throwable;

/**
 * Purpose: enforce distributed tracing middleware concerns around request handling.
 * How: runs before and/or after the next pipeline stage and may short-circuit with a response.
 * Used in framework: registered in request pipelines to apply cross-cutting behavior per request.
 */
final class DistributedTracingMiddleware implements MiddlewareInterface
{
   /**
    * Create a new instance.
    *
    * @param string $serviceName
    * @param TracerInterface $tracer
    * @param TracePropagatorInterface $propagator
    * @return mixed
    */
   public function __construct(
      private string $serviceName,
      private TracerInterface $tracer,
      private TracePropagatorInterface $propagator,
   ) {
   }

   /**
    * Handle handle.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @param callable $next
    * @return Response
    */
   public function handle(RequestContext $ctx, Request $request, callable $next): Response
   {
      $existing = $ctx->getAttribute('trace.context');
      $incoming = $this->propagator->extract($request, $this->serviceName);

      $parent = null;
      if ($existing instanceof TraceContext) {
         $parent = $existing;
      } elseif ($incoming instanceof TraceContext) {
         $parent = $incoming;
      }

      $traceContext = $parent instanceof TraceContext
         ? $parent->child($this->serviceName)
         : TraceContext::root($this->serviceName);

      $span = $this->tracer->startSpan(
         $traceContext,
         sprintf('%s %s %s', $this->serviceName, $request->getMethod(), $request->getPath()),
         [
            'service' => $this->serviceName,
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
         ]
      );

      $nextContext = $ctx
         ->withAttribute('trace.context', $traceContext)
         ->withAttribute('trace.span', $span);

      try {
         $response = $next($nextContext, $request);
         $this->tracer->endSpan($span, ['http.status_code' => $response->getStatus()]);
         return $this->propagator->injectResponse($response, $traceContext);
      } catch (Throwable $exception) {
         $this->tracer->endSpan($span, [
            'error' => true,
            'error.type' => $exception::class,
            'error.message' => $exception->getMessage(),
         ]);

         throw $exception;
      }
   }
}



