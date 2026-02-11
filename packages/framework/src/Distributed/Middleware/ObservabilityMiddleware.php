<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Middleware;

use Celeris\Framework\Distributed\Observability\ObservabilityDispatcher;
use Celeris\Framework\Distributed\Tracing\TraceContext;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;
use Throwable;

/**
 * Purpose: enforce observability middleware concerns around request handling.
 * How: runs before and/or after the next pipeline stage and may short-circuit with a response.
 * Used in framework: registered in request pipelines to apply cross-cutting behavior per request.
 */
final class ObservabilityMiddleware implements MiddlewareInterface
{
   /**
    * Create a new instance.
    *
    * @param string $serviceName
    * @param ObservabilityDispatcher $dispatcher
    * @return mixed
    */
   public function __construct(
      private string $serviceName,
      private ObservabilityDispatcher $dispatcher,
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
      $startedAt = microtime(true);
      $this->dispatcher->emit('request.start', [
         'service' => $this->serviceName,
         'request_id' => $ctx->getAttribute('request.id', $ctx->getRequestId()),
         'method' => $request->getMethod(),
         'path' => $request->getPath(),
      ]);

      try {
         $response = $next($ctx, $request);
         $traceId = null;
         $traceContext = $ctx->getAttribute('trace.context');
         if ($traceContext instanceof TraceContext) {
            $traceId = $traceContext->traceId();
         }

         $this->dispatcher->emit('request.finish', [
            'service' => $this->serviceName,
            'request_id' => $ctx->getAttribute('request.id', $ctx->getRequestId()),
            'status' => $response->getStatus(),
            'duration_ms' => (microtime(true) - $startedAt) * 1000,
            'trace_id' => $traceId,
         ]);

         return $response;
      } catch (Throwable $exception) {
         $this->dispatcher->emit('request.error', [
            'service' => $this->serviceName,
            'request_id' => $ctx->getAttribute('request.id', $ctx->getRequestId()),
            'duration_ms' => (microtime(true) - $startedAt) * 1000,
            'error_type' => $exception::class,
            'error_message' => $exception->getMessage(),
         ]);

         throw $exception;
      }
   }
}



