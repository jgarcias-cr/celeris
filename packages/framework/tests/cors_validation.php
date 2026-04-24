<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Http\Cors\CorsPreflightMiddleware;
use Celeris\Framework\Http\Cors\CorsResponseFinalizer;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Kernel\Kernel;

/**
 * @param array<string, mixed> $cors
 */
function buildKernel(array $cors): Kernel
{
   $configDir = createConfigDirectory($cors);

   $kernel = new Kernel(
      configLoader: new ConfigLoader($configDir),
      hotReloadEnabled: false,
      registerBuiltinRoutes: false,
   );
   $kernel->getPipeline()->add(new CorsPreflightMiddleware());
   $kernel->getResponsePipeline()->add(new CorsResponseFinalizer());

   $kernel->routes()->get('/api/ping', static fn (): Response => new Response(200, ['content-type' => 'text/plain; charset=utf-8'], 'pong'));
   $kernel->routes()->get('/health', static fn (): Response => new Response(200, ['content-type' => 'text/plain; charset=utf-8'], 'ok'));

   return $kernel;
}

/**
 * @param array<string, mixed> $cors
 */
function createConfigDirectory(array $cors): string
{
   $dir = '/tmp/celeris-cors-' . bin2hex(random_bytes(6));
   if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
      throw new RuntimeException(sprintf('Unable to create temp config dir "%s".', $dir));
   }

   $config = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($cors, true) . ";\n";
   file_put_contents($dir . '/cors.php', $config);

   return $dir;
}

function perform(Kernel $kernel, Request $request): Response
{
   $ctx = new RequestContext(bin2hex(random_bytes(8)), microtime(true), $request->getServerParams());
   $response = $kernel->handle($ctx, $request);
   $kernel->reset();
   return $response;
}

function assertTrue(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

function runAllowedPreflightTest(): void
{
   $kernel = buildKernel([
      'paths' => ['/api/*'],
      'allowed_origins' => ['http://localhost:3000'],
      'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
      'allowed_headers' => ['Authorization', 'Content-Type', 'X-Api-Version'],
      'exposed_headers' => [],
      'supports_credentials' => false,
      'max_age' => 600,
   ]);

   $response = perform($kernel, new Request(
      'OPTIONS',
      '/api/ping',
      [
         'origin' => 'http://localhost:3000',
         'access-control-request-method' => 'POST',
         'access-control-request-headers' => 'Authorization, X-Api-Version',
      ],
   ));

   assertTrue($response->getStatus() === 204, 'Allowed preflight should return 204.');
   assertTrue($response->getHeader('access-control-allow-origin') === 'http://localhost:3000', 'Allowed preflight should reflect the configured origin.');
   assertTrue(str_contains((string) $response->getHeader('access-control-allow-methods', ''), 'POST'), 'Allowed preflight should emit configured methods.');
   assertTrue(str_contains((string) $response->getHeader('access-control-allow-headers', ''), 'Authorization'), 'Allowed preflight should emit configured headers.');
   assertTrue(str_contains((string) $response->getHeader('vary', ''), 'Origin'), 'Allowed preflight should vary by origin.');
}

function runDeniedPreflightTest(): void
{
   $kernel = buildKernel([
      'paths' => ['/api/*'],
      'allowed_origins' => ['http://localhost:3000'],
      'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
      'allowed_headers' => ['Authorization'],
      'exposed_headers' => [],
      'supports_credentials' => false,
      'max_age' => 600,
   ]);

   $response = perform($kernel, new Request(
      'OPTIONS',
      '/api/ping',
      [
         'origin' => 'https://evil.example',
         'access-control-request-method' => 'POST',
      ],
   ));

   assertTrue($response->getStatus() === 403, 'Denied preflight should return 403.');
   assertTrue($response->getHeader('access-control-allow-origin') === null, 'Denied preflight should not emit allow-origin.');
}

function runActualResponseHeaderTest(): void
{
   $kernel = buildKernel([
      'paths' => ['/api/*'],
      'allowed_origins' => ['http://localhost:3000'],
      'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
      'allowed_headers' => ['Authorization'],
      'exposed_headers' => ['X-Trace-Id'],
      'supports_credentials' => true,
      'max_age' => 600,
   ]);

   $response = perform($kernel, new Request(
      'GET',
      '/api/ping',
      ['origin' => 'http://localhost:3000'],
   ));

   assertTrue($response->getStatus() === 200, 'Actual request should reach the handler.');
   assertTrue($response->getHeader('access-control-allow-origin') === 'http://localhost:3000', 'Actual response should include allow-origin.');
   assertTrue($response->getHeader('access-control-allow-credentials') === 'true', 'Actual response should include credentials when enabled.');
   assertTrue($response->getHeader('access-control-expose-headers') === 'X-Trace-Id', 'Actual response should expose configured headers.');
}

function runNotFoundHeaderTest(): void
{
   $kernel = buildKernel([
      'paths' => ['/api/*'],
      'allowed_origins' => ['http://localhost:3000'],
      'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
      'allowed_headers' => ['Authorization'],
      'exposed_headers' => [],
      'supports_credentials' => false,
      'max_age' => 600,
   ]);

   $response = perform($kernel, new Request(
      'GET',
      '/api/missing',
      ['origin' => 'http://localhost:3000'],
   ));

   assertTrue($response->getStatus() === 404, 'Missing API route should still be 404.');
   assertTrue($response->getHeader('access-control-allow-origin') === 'http://localhost:3000', 'Missing API route should still receive CORS headers.');
}

function runPathBypassTest(): void
{
   $kernel = buildKernel([
      'paths' => ['/api/*'],
      'allowed_origins' => ['*'],
      'allowed_methods' => ['GET', 'OPTIONS'],
      'allowed_headers' => ['*'],
      'exposed_headers' => [],
      'supports_credentials' => false,
      'max_age' => 600,
   ]);

   $response = perform($kernel, new Request(
      'GET',
      '/health',
      ['origin' => 'http://localhost:3000'],
   ));

   assertTrue($response->getStatus() === 200, 'Non-API route should still resolve normally.');
   assertTrue($response->getHeader('access-control-allow-origin') === null, 'Non-matching path should bypass CORS headers.');
}

$checks = [
   'AllowedPreflight' => 'runAllowedPreflightTest',
   'DeniedPreflight' => 'runDeniedPreflightTest',
   'ActualResponseHeaders' => 'runActualResponseHeaderTest',
   'NotFoundHeaders' => 'runNotFoundHeaderTest',
   'PathBypass' => 'runPathBypassTest',
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
