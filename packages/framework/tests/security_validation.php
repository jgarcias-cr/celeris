<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Security\Auth\AuthEngine;
use Celeris\Framework\Security\Auth\CookieSessionStrategy;
use Celeris\Framework\Security\Auth\InMemoryOpaqueTokenStore;
use Celeris\Framework\Security\Auth\InMemorySessionStore;
use Celeris\Framework\Security\Auth\InMemoryTokenRevocationStore;
use Celeris\Framework\Security\Auth\OpaqueTokenStrategy;
use Celeris\Framework\Security\Auth\StoredCredential;
use Celeris\Framework\Security\Authorization\Authorize;
use Celeris\Framework\Security\Authorization\PolicyEngine;
use Celeris\Framework\Security\Csrf\CsrfProtector;
use Celeris\Framework\Security\Input\InputNormalizer;
use Celeris\Framework\Security\Input\RequestValidator;
use Celeris\Framework\Security\Input\SqlInjectionGuard;
use Celeris\Framework\Security\Password\PasswordHasher;
use Celeris\Framework\Security\RateLimit\RateLimiter;
use Celeris\Framework\Security\Response\SecurityHeadersFinalizer;
use Celeris\Framework\Security\SecurityKernelGuard;

/**
 * Represents the security validation controller component for this file.
 */
final class SecurityValidationController
{
   #[Authorize(roles: ['admin'])]
   /**
    * Handle admin.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   public function admin(RequestContext $ctx, Request $request): Response
   {
      return new Response(200, ['content-type' => 'text/plain; charset=utf-8'], 'admin-ok');
   }

   #[Authorize]
   /**
    * Handle secure.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   public function secure(RequestContext $ctx, Request $request): Response
   {
      return new Response(200, ['content-type' => 'text/plain; charset=utf-8'], 'secure-ok');
   }

   #[Authorize(strategies: ['cookie_session'])]
   /**
    * Handle transfer.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return Response
    */
   public function transfer(RequestContext $ctx, Request $request): Response
   {
      return new Response(200, ['content-type' => 'text/plain; charset=utf-8'], 'transfer-ok');
   }

   public function public(RequestContext $ctx, Request $request): Response
   {
      return new Response(200, ['content-type' => 'text/plain; charset=utf-8'], 'public-ok');
   }
}

/**
 * @return array{Kernel, InMemoryTokenRevocationStore}
 */
function buildKernel(int $rateLimit = 120): array
{
   $opaqueStore = new InMemoryOpaqueTokenStore([
      'opaque-admin' => new StoredCredential('admin-user', ['admin'], ['admin:read'], ['src' => 'test'], null, 'opaque-admin-token'),
      'opaque-user' => new StoredCredential('basic-user', ['user'], ['profile:read'], ['src' => 'test'], null, 'opaque-user-token'),
   ]);

   $sessionStore = new InMemorySessionStore([
      'sess-1' => new StoredCredential('session-user', ['user'], ['transfer:write'], ['src' => 'test'], null, 'session-token'),
   ]);

   $revocationStore = new InMemoryTokenRevocationStore();

   $authEngine = new AuthEngine([
      new OpaqueTokenStrategy($opaqueStore),
      new CookieSessionStrategy($sessionStore),
   ], $revocationStore);

   $guard = new SecurityKernelGuard(
      $authEngine,
      new PolicyEngine(),
      new CsrfProtector(true),
      new InputNormalizer(),
      new RequestValidator(),
      new SqlInjectionGuard(),
      new RateLimiter($rateLimit, 60, 0),
      new SecurityHeadersFinalizer(),
      new PasswordHasher('bcrypt', ['cost' => 10]),
   );

   $kernel = new Kernel(
      configLoader: null,
      hotReloadEnabled: false,
      securityGuard: $guard,
   );
   $kernel->setConfigLoader(null);

   $kernel->routes()->get('/admin', [SecurityValidationController::class, 'admin']);
   $kernel->routes()->get('/secure', [SecurityValidationController::class, 'secure']);
   $kernel->routes()->post('/transfer', [SecurityValidationController::class, 'transfer']);
   $kernel->routes()->get('/public', [SecurityValidationController::class, 'public']);

   return [$kernel, $revocationStore];
}

/**
 * Handle perform.
 *
 * @param Kernel $kernel
 * @param Request $request
 * @return Response
 */
function perform(Kernel $kernel, Request $request): Response
{
   $ctx = new RequestContext(bin2hex(random_bytes(8)), microtime(true), $request->getServerParams());
   $response = $kernel->handle($ctx, $request);
   $kernel->reset();
   return $response;
}

/**
 * Handle assert status.
 *
 * @param string $label
 * @param Response $response
 * @param int $expected
 * @return void
 */
function assertStatus(string $label, Response $response, int $expected): void
{
   if ($response->getStatus() !== $expected) {
      throw new RuntimeException(sprintf('%s: expected %d, got %d (%s)', $label, $expected, $response->getStatus(), $response->getBody()));
   }
}

/**
 * Handle run pen test simulation.
 *
 * @return void
 */
function runPenTestSimulation(): void
{
   [$kernel] = buildKernel(120);

   $sqlProbe = perform(
      $kernel,
      new Request(
         'GET',
         '/admin',
         ['authorization' => 'Bearer opaque-admin'],
         ['id' => '1 OR 1=1 --'],
         '',
         [],
         [],
         null,
         ['REMOTE_ADDR' => '10.0.0.1'],
      )
   );
   assertStatus('pen-test sql injection blocked', $sqlProbe, 400);

   $csrfProbe = perform(
      $kernel,
      new Request(
         'POST',
         '/transfer',
         [],
         [],
         '',
         ['session_id' => 'sess-1'],
         [],
         ['amount' => 100],
         ['REMOTE_ADDR' => '10.0.0.1'],
      )
   );
   assertStatus('pen-test csrf blocked', $csrfProbe, 403);
}

/**
 * Handle run token invalidation test.
 *
 * @return void
 */
function runTokenInvalidationTest(): void
{
   [$kernel, $revocations] = buildKernel(120);
   $revocations->revoke('opaque-admin-token');

   $response = perform(
      $kernel,
      new Request(
         'GET',
         '/secure',
         ['authorization' => 'Bearer opaque-admin'],
         [],
         '',
         [],
         [],
         null,
         ['REMOTE_ADDR' => '10.0.0.2'],
      )
   );

   assertStatus('token invalidation', $response, 401);
}

/**
 * Handle run rate limit load test.
 *
 * @return void
 */
function runRateLimitLoadTest(): void
{
   $limit = 25;
   [$kernel] = buildKernel($limit);

   $tooMany = 0;
   $ok = 0;
   for ($i = 0; $i < 60; $i++) {
      $response = perform(
         $kernel,
         new Request(
            'GET',
            '/public',
            [],
            [],
            '',
            [],
            [],
            null,
            ['REMOTE_ADDR' => '10.20.30.40'],
         )
      );

      if ($response->getStatus() === 429) {
         $tooMany++;
      } elseif ($response->getStatus() === 200) {
         $ok++;
      } else {
         throw new RuntimeException(sprintf('rate-limit: unexpected status %d', $response->getStatus()));
      }
   }

   if ($ok !== $limit || $tooMany !== 35) {
      throw new RuntimeException(sprintf('rate-limit: expected ok=%d and 429=35, got ok=%d and 429=%d', $limit, $ok, $tooMany));
   }
}

$checks = [
   'PenTest' => 'runPenTestSimulation',
   'TokenInvalidation' => 'runTokenInvalidationTest',
   'RateLimitLoad' => 'runRateLimitLoadTest',
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


