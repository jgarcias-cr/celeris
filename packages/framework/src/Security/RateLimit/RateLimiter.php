<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\RateLimit;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Security\SecurityException;

/**
 * Implement rate limiter behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RateLimiter
{
   /** @var array<string, array{count:int, reset_at:float}> */
   private array $buckets = [];
   private int $enforcements = 0;

   /**
    * Create a new instance.
    *
    * @param int $limit
    * @param int $windowSeconds
    * @param int $burst
    * @return mixed
    */
   public function __construct(
      private int $limit = 120,
      private int $windowSeconds = 60,
      private int $burst = 0,
   ) {
   }

   /**
    * Handle enforce.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return void
    */
   public function enforce(RequestContext $ctx, Request $request): void
   {
      if ($this->limit <= 0 || $this->windowSeconds <= 0) {
         return;
      }

      $now = microtime(true);
      $key = $this->resolveKey($ctx, $request);
      $bucket = $this->buckets[$key] ?? ['count' => 0, 'reset_at' => $now + $this->windowSeconds];

      if ($now >= $bucket['reset_at']) {
         $bucket = ['count' => 0, 'reset_at' => $now + $this->windowSeconds];
      }

      $allowed = $this->limit + max($this->burst, 0);
      if ($bucket['count'] >= $allowed) {
         $retryAfter = max(1, (int) ceil($bucket['reset_at'] - $now));
         throw new SecurityException(
            'Rate limit exceeded.',
            429,
            [
               'retry-after' => (string) $retryAfter,
               'x-ratelimit-limit' => (string) $allowed,
               'x-ratelimit-remaining' => '0',
            ],
         );
      }

      $bucket['count']++;
      $this->buckets[$key] = $bucket;

      $this->enforcements++;
      if (($this->enforcements % 256) === 0) {
         $this->prune($now);
      }
   }

   /**
    * Handle reset.
    *
    * @return void
    */
   public function reset(): void
   {
      $this->buckets = [];
      $this->enforcements = 0;
   }

   /**
    * Handle resolve key.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return string
    */
   private function resolveKey(RequestContext $ctx, Request $request): string
   {
      $server = $request->getServerParams();
      $forwarded = trim((string) ($request->getHeader('x-forwarded-for') ?? ''));
      if ($forwarded !== '') {
         $parts = explode(',', $forwarded);
         $ip = trim($parts[0]);
      } else {
         $ip = trim((string) ($server['REMOTE_ADDR'] ?? ''));
      }

      if ($ip === '') {
         $ip = 'req:' . $ctx->getRequestId();
      }

      return $ip;
   }

   /**
    * Handle prune.
    *
    * @param float $now
    * @return void
    */
   private function prune(float $now): void
   {
      foreach ($this->buckets as $key => $bucket) {
         if ($now >= $bucket['reset_at']) {
            unset($this->buckets[$key]);
         }
      }
   }
}



