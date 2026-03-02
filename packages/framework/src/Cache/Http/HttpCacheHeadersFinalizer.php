<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Http;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\ResponseFinalizerInterface;

/**
 * Implement http cache headers finalizer behavior for the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class HttpCacheHeadersFinalizer implements ResponseFinalizerInterface
{
   /**
    * Create a new instance.
    *
    * @param HttpCachePolicy $defaultPolicy
    * @return mixed
    */
   public function __construct(private HttpCachePolicy $defaultPolicy = new HttpCachePolicy())
   {
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
      $policy = $ctx->getAttribute('http.cache.policy');
      if (!$policy instanceof HttpCachePolicy) {
         $policy = $this->defaultPolicy;
      }

      $final = $response;

      if ($final->getHeader('cache-control') === null) {
         $final = $final->withHeader('cache-control', $policy->toCacheControl());
      }

      if ($final->getHeader('vary') === null && $policy->vary() !== []) {
         $final = $final->withHeader('vary', implode(', ', $policy->vary()));
      }

      if ($final->getHeader('etag') === null && !$final->isStreaming()) {
         $etag = 'W/"' . sha1($final->getBody()) . '"';
         $final = $final->withHeader('etag', $etag);
      }

      if ($final->getHeader('last-modified') === null) {
         $final = $final->withHeader('last-modified', gmdate('D, d M Y H:i:s') . ' GMT');
      }

      return $final;
   }
}



