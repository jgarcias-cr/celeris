<?php

declare(strict_types=1);

namespace Celeris\Framework\Http\Cors;

use Celeris\Framework\Http\HttpStatus;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;

/**
 * Handles browser CORS preflight requests before routing.
 */
final class CorsPreflightMiddleware implements MiddlewareInterface
{
   /**
    * Create the CORS preflight middleware with an optional explicit policy.
    */
   public function __construct(private ?CorsPolicy $policy = null)
   {
      $this->policy ??= new CorsPolicy();
   }

   /**
    * Short-circuit valid preflight requests before the main handler runs.
    */
   public function handle(RequestContext $ctx, Request $request, callable $next): Response
   {
      $decision = $this->policy->evaluate($ctx, $request);
      if (($decision['preflight'] ?? false) !== true || ($decision['path_matched'] ?? false) !== true) {
         return $next($ctx, $request);
      }

      if (($decision['preflight_allowed'] ?? false) !== true) {
         return new Response(
            HttpStatus::FORBIDDEN,
            ['content-type' => 'text/plain; charset=utf-8'],
            'CORS preflight denied'
         );
      }

      return $this->policy->applyPreflightHeaders(new Response(HttpStatus::NO_CONTENT), $decision);
   }
}
