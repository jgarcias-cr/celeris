<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;

/**
 * Purpose: implement w3 c trace context propagator behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when w3 c trace context propagator functionality is required.
 */
final class W3CTraceContextPropagator implements TracePropagatorInterface
{
   /**
    * Handle extract.
    *
    * @param Request $request
    * @param string $service
    * @return ?TraceContext
    */
   public function extract(Request $request, string $service): ?TraceContext
   {
      $traceParent = $request->getHeader('traceparent');
      if (!is_string($traceParent) || trim($traceParent) === '') {
         return null;
      }

      $parts = explode('-', trim($traceParent));
      if (count($parts) !== 4) {
         return null;
      }

      [$version, $traceId, $parentSpanId, $flags] = $parts;
      if ($version !== '00') {
         return null;
      }

      if (!preg_match('/^[a-f0-9]{32}$/', $traceId)) {
         return null;
      }
      if (!preg_match('/^[a-f0-9]{16}$/', $parentSpanId)) {
         return null;
      }
      if (!preg_match('/^[a-f0-9]{2}$/', $flags)) {
         return null;
      }

      $sampled = (hexdec($flags) & 0x01) === 0x01;
      return new TraceContext(strtolower($traceId), bin2hex(random_bytes(8)), strtolower($parentSpanId), $sampled, $service);
   }

   /**
    * Handle inject request.
    *
    * @param Request $request
    * @param TraceContext $context
    * @return Request
    */
   public function injectRequest(Request $request, TraceContext $context): Request
   {
      return $request->withHeaders([
         ...$request->getHeaders(),
         'traceparent' => $context->toTraceParent(),
         'x-trace-id' => $context->traceId(),
      ]);
   }

   /**
    * Handle inject response.
    *
    * @param Response $response
    * @param TraceContext $context
    * @return Response
    */
   public function injectResponse(Response $response, TraceContext $context): Response
   {
      $withTrace = $response->withHeader('traceparent', $context->toTraceParent());
      return $withTrace->withHeader('x-trace-id', $context->traceId());
   }
}



