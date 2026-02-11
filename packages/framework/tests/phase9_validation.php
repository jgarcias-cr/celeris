<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Cache\CacheEngine;
use Celeris\Framework\Cache\Http\HttpCacheContext;
use Celeris\Framework\Cache\Http\HttpCacheHeadersFinalizer;
use Celeris\Framework\Cache\Http\HttpCachePolicy;
use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Cache\Store\FileTagVersionState;
use Celeris\Framework\Cache\Store\InMemoryCacheStore;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;

/**
 * Handle assert true.
 *
 * @param bool $condition
 * @param string $message
 * @return void
 */
function assertTrue(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

/**
 * Handle create engine.
 *
 * @param string $statePath
 * @param string $scope
 * @return CacheEngine
 */
function createEngine(string $statePath, string $scope): CacheEngine
{
   $store = new InMemoryCacheStore(new FileTagVersionState($statePath), $scope);
   return new CacheEngine($store, new Celeris\Framework\Cache\Invalidation\DeterministicInvalidationEngine());
}

/**
 * Handle run cache coherency tests.
 *
 * @return void
 */
function runCacheCoherencyTests(): void
{
   $statePath = '/tmp/celeris-phase9-coherency-' . bin2hex(random_bytes(6)) . '.json';
   @unlink($statePath);

   $engine = createEngine($statePath, 'coherency');

   $intent = CacheIntent::read('users', 'u:1', ttlSeconds: 120, tags: ['user:1']);
   $calls = 0;

   $first = $engine->remember($intent, function () use (&$calls): array {
      $calls++;
      return ['id' => 1, 'name' => 'Ari'];
   });

   $second = $engine->remember($intent, function () use (&$calls): array {
      $calls++;
      return ['id' => 1, 'name' => 'Ari-NEW'];
   });

   assertTrue($calls === 1, 'Cache coherency: repeated remember() must not recompute when entry is valid.');
   assertTrue($first === $second, 'Cache coherency: cached result should be stable.');

   $engine->invalidate(CacheIntent::invalidate('users', '*', ['user:1']));

   $third = $engine->remember($intent, function () use (&$calls): array {
      $calls++;
      return ['id' => 1, 'name' => 'Ari-UPDATED'];
   });

   assertTrue($calls === 2, 'Cache coherency: deterministic invalidation must force recomputation exactly once.');
   assertTrue(($third['name'] ?? '') === 'Ari-UPDATED', 'Cache coherency: recomputed value should be returned after invalidation.');
}

/**
 * Handle run multi worker invalidation safety tests.
 *
 * @return void
 */
function runMultiWorkerInvalidationSafetyTests(): void
{
   $statePath = '/tmp/celeris-phase9-workers-' . bin2hex(random_bytes(6)) . '.json';
   @unlink($statePath);

   $workerA = createEngine($statePath, 'workers');
   $workerB = createEngine($statePath, 'workers');

   $intent = CacheIntent::read('inventory', 'sku-100', ttlSeconds: 300, tags: ['inventory:sku-100']);

   $workerA->put($intent, ['sku' => 'sku-100', 'stock' => 12]);
   $workerB->put($intent, ['sku' => 'sku-100', 'stock' => 12]);

   $before = $workerB->get($intent);
   assertTrue(is_array($before) && ($before['stock'] ?? null) === 12, 'Worker B should read its local cached value before invalidation.');

   $workerA->invalidate(CacheIntent::invalidate('inventory', '*', ['inventory:sku-100']));

   $afterInvalidation = $workerB->get($intent);
   assertTrue($afterInvalidation === null, 'Worker B local cache must become invalid after worker A tag invalidation.');

   $recomputed = $workerB->remember($intent, static fn (): array => ['sku' => 'sku-100', 'stock' => 9]);
   assertTrue(($recomputed['stock'] ?? null) === 9, 'Worker B should repopulate cache deterministically after invalidation.');
}

/**
 * Handle run http cache header tests.
 *
 * @return void
 */
function runHttpCacheHeaderTests(): void
{
   $finalizer = new HttpCacheHeadersFinalizer(new HttpCachePolicy(public: true, maxAge: 120, staleWhileRevalidate: 30));

   $ctx = new RequestContext('phase9-http', microtime(true), ['REMOTE_ADDR' => '127.0.0.1']);
   $intent = CacheIntent::read('http', 'home', ttlSeconds: 120, tags: ['page:home'])
      ->withPublic(true)
      ->withStaleWhileRevalidate(30);

   $ctx = HttpCacheContext::withIntent($ctx, $intent);

   $request = new Request('GET', '/home', ['accept' => 'application/json'], [], '');
   $response = new Response(200, ['content-type' => 'application/json; charset=utf-8'], '{"ok":true}');

   $final = $finalizer->finalize($ctx, $request, $response);

   $cacheControl = $final->getHeader('cache-control');
   $etag = $final->getHeader('etag');
   $vary = $final->getHeader('vary');

   assertTrue(is_string($cacheControl) && str_contains($cacheControl, 'max-age=120'), 'HTTP cache finalizer should set cache-control from policy.');
   assertTrue(is_string($etag) && str_starts_with($etag, 'W/"'), 'HTTP cache finalizer should emit deterministic ETag for non-streaming response.');
   assertTrue(is_string($vary) && str_contains($vary, 'accept'), 'HTTP cache finalizer should emit vary header from policy.');
}

$checks = [
   'CacheCoherency' => 'runCacheCoherencyTests',
   'MultiWorkerInvalidation' => 'runMultiWorkerInvalidationSafetyTests',
   'HttpCacheHeaders' => 'runHttpCacheHeaderTests',
];

$failed = false;
foreach ($checks as $name => $fn) {
   try {
      $fn();
      echo "[PASS] {$name}\n";
   } catch (Throwable $exception) {
      $failed = true;
      echo "[FAIL] {$name}: {$exception->getMessage()}\n";
   }
}

exit($failed ? 1 : 0);

