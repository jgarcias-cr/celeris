<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Response;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\ResponseFinalizerInterface;

/**
 * Implement security headers finalizer behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class SecurityHeadersFinalizer implements ResponseFinalizerInterface
{
   /** @var array<string, string> */
   private array $defaultHeaders;

   /**
    * @param array<string, string> $defaultHeaders
    */
   public function __construct(array $defaultHeaders = [])
   {
      $this->defaultHeaders = $defaultHeaders !== [] ? $defaultHeaders : [
         'x-content-type-options' => 'nosniff',
         'x-frame-options' => 'DENY',
         'referrer-policy' => 'no-referrer',
         'x-xss-protection' => '0',
         'content-security-policy' => "default-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'",
         'permissions-policy' => 'camera=(), microphone=(), geolocation=()',
      ];
   }

   /**
    * Handle finalize.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @param Response $response
    * @return Response
    */
   public function finalize(RequestContext $ctx, Request $request, Response $response): Response
   {
      $secured = $response;
      foreach ($this->defaultHeaders as $name => $value) {
         if ($secured->getHeader($name) !== null) {
            continue;
         }
         $secured = $secured->withHeader($name, $value);
      }

      return $secured;
   }
}



