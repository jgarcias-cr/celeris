<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;

/**
 * Enforce request id middleware concerns around request handling.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
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
      $incomingId = trim((string) ($request->getHeader('x-request-id') ?? ''));
      $requestId = $incomingId !== '' ? $incomingId : $ctx->getRequestId();

      $nextContext = $ctx->withAttribute('request.id', $requestId);
      $nextRequest = $request;
      if ($incomingId === '') {
         $nextRequest = $request->withHeaders([
            ...$request->getHeaders(),
            'x-request-id' => $requestId,
         ]);
      }

      $response = $next($nextContext, $nextRequest);
      if ($response->getHeader('x-request-id') === null) {
         $response = $response->withHeader('x-request-id', $requestId);
      }

      return $response;
   }
}



