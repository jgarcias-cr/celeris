<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Http;

use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Http\RequestContext;

/**
 * Carry http cache context state across a single execution scope.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class HttpCacheContext
{
   /**
    * Return a copy with the intent.
    *
    * @param RequestContext $ctx
    * @param CacheIntent $intent
    * @return RequestContext
    */
   public static function withIntent(RequestContext $ctx, CacheIntent $intent): RequestContext
   {
      $policy = new HttpCachePolicy(
         public: $intent->isPublic(),
         maxAge: max(0, $intent->ttlSeconds() ?? 0),
         staleWhileRevalidate: $intent->staleWhileRevalidateSeconds(),
         mustRevalidate: !$intent->allowStale(),
      );

      return $ctx
         ->withAttribute('http.cache.policy', $policy)
         ->withAttribute('cache.intent', $intent);
   }
}



