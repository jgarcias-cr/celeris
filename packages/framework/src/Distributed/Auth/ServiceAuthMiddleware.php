<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Auth;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;

/**
 * Enforce service auth middleware concerns around request handling.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ServiceAuthMiddleware implements MiddlewareInterface
{
   /**
    * Create a new instance.
    *
    * @param ServiceAuthenticator $authenticator
    * @param bool $required
    * @return mixed
    */
   public function __construct(private ServiceAuthenticator $authenticator, private bool $required = true) {}

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
      $hasAuthHeaders = $request->getHeader('x-service-signature') !== null
         || $request->getHeader('x-service-id') !== null;

      if (!$this->required && !$hasAuthHeaders) {
         return $next($ctx, $request);
      }

      $result = $this->authenticator->authenticate($request);
      if (!$result->authenticated()) {
         return new Response(
            401,
            [
               'content-type' => 'application/json; charset=utf-8',
               'www-authenticate' => 'Service realm="internal"',
            ],
            (string) json_encode([
               'error' => 'service_auth_failed',
               'message' => $result->error(),
            ], JSON_UNESCAPED_SLASHES)
         );
      }

      $principal = $result->principal();
      $nextContext = $ctx;
      if ($principal instanceof ServicePrincipal) {
         $nextContext = $ctx->withAttribute('service.principal', $principal);
      }

      return $next($nextContext, $request);
   }
}



