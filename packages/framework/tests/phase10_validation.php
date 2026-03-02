<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Cli\ToolingCliApplication;
use Celeris\Framework\Tooling\Diff\UnifiedDiffBuilder;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
use Celeris\Framework\Routing\RouteDefinition;
use Celeris\Framework\Tooling\Web\DeveloperUiController;

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
 * Handle run ux testing.
 *
 * @return void
 */
function runUxTesting(): void
{
   $_ENV['APP_ENV'] = 'development';
   $_SERVER['APP_ENV'] = 'development';

   $tmpRoot = '/tmp/celeris-phase10-ui-' . bin2hex(random_bytes(6));
   mkdir($tmpRoot, 0777, true);

   $generator = new GeneratorEngine();
   $graphBuilder = new DependencyGraphBuilder(__DIR__ . '/../src');
   $validator = new ArchitectureDecisionValidator();

   $controller = new DeveloperUiController(
      $generator,
      $graphBuilder,
      $validator,
      $tmpRoot,
      '/__dev/tooling',
      'App',
      static fn (): array => [
         new RouteDefinition('GET', '/health', static fn (): string => 'ok', ['auth']),
      ],
   );

   $ctx = new RequestContext('phase10-ui', microtime(true), ['REMOTE_ADDR' => '127.0.0.1']);
   $dashboardRequest = new Request('GET', '/__dev/tooling', [], [
      'generator' => 'controller',
      'name' => 'PreviewUser',
      'module' => 'Demo',
   ]);

   $dashboardResponse = $controller->handle($ctx, $dashboardRequest);
   assertTrue($dashboardResponse->getStatus() === 200, 'Dashboard route should return 200.');
   assertTrue(
      str_contains((string) $dashboardResponse->getHeader('content-type'), 'text/html'),
      'Dashboard route should return HTML.'
   );
   assertTrue(
      str_contains($dashboardResponse->getBody(), 'Celeris Tooling Platform'),
      'Dashboard should include tooling platform heading.'
   );
   assertTrue(
      str_contains($dashboardResponse->getBody(), 'Generate APP_KEY'),
      'Dashboard should expose APP_KEY generator action.'
   );

   $previewRequest = new Request('GET', '/__dev/tooling/generate/preview', [], [
      'generator' => 'controller',
      'name' => 'PreviewUser',
      'module' => 'Demo',
   ]);
   $previewResponse = $controller->handle($ctx, $previewRequest);
   assertTrue($previewResponse->getStatus() === 200, 'Generator preview endpoint should return 200.');

   $payload = json_decode($previewResponse->getBody(), true);
   assertTrue(is_array($payload), 'Generator preview endpoint should return JSON payload.');
   assertTrue(isset($payload['files'][0]['path']), 'Generator preview payload should include files.');
   $previewFiles = is_array($payload['files'] ?? null) ? $payload['files'] : [];
   $joinedDiffs = implode("\n", array_map(
      static fn (mixed $row): string => is_array($row) && is_string($row['diff'] ?? null) ? $row['diff'] : '',
      $previewFiles,
   ));
   assertTrue(
      str_contains($joinedDiffs, '+++ b/src/Demo/Controller/Base/PreviewUserControllerBase.php')
      && str_contains($joinedDiffs, '+++ b/src/Demo/Controller/PreviewUserController.php'),
      'Generator preview payload should include a visual diff.'
   );

   $summaryRequest = new Request('GET', '/__dev/tooling/api/v1/summary');
   $summaryResponse = $controller->handle($ctx, $summaryRequest);
   assertTrue($summaryResponse->getStatus() === 200, 'Versioned summary endpoint should return 200.');

   $summaryPayload = json_decode($summaryResponse->getBody(), true);
   assertTrue(is_array($summaryPayload), 'Versioned summary endpoint should return JSON payload.');
   assertTrue(($summaryPayload['status'] ?? null) === 'ok', 'Versioned summary endpoint should return an ok envelope.');
   assertTrue(
      isset($summaryPayload['data']['generators']) && is_array($summaryPayload['data']['generators']),
      'Versioned summary endpoint should include generators in data.'
   );

   $routesRequest = new Request('GET', '/__dev/tooling/api/v1/routes');
   $routesResponse = $controller->handle($ctx, $routesRequest);
   assertTrue($routesResponse->getStatus() === 200, 'Routes endpoint should return 200.');
   $routesPayload = json_decode($routesResponse->getBody(), true);
   assertTrue(is_array($routesPayload), 'Routes endpoint should return JSON payload.');
   $routeItems = is_array($routesPayload['data']['items'] ?? null) ? $routesPayload['data']['items'] : [];
   assertTrue($routeItems !== [], 'Routes endpoint should include at least one route row.');

   $applyRequest = new Request(
      'POST',
      '/__dev/tooling/api/v1/generate/apply',
      ['content-type' => 'application/json'],
      [],
      (string) json_encode([
         'generator' => 'controller',
         'name' => 'AppliedUser',
         'module' => 'Demo',
         'overwrite' => true,
      ], JSON_UNESCAPED_SLASHES)
   );
   $applyResponse = $controller->handle($ctx, $applyRequest);
   assertTrue($applyResponse->getStatus() === 200, 'Versioned apply endpoint should return 200.');

   $applyPayload = json_decode($applyResponse->getBody(), true);
   assertTrue(is_array($applyPayload), 'Versioned apply endpoint should return JSON payload.');
   assertTrue(($applyPayload['status'] ?? null) === 'ok', 'Versioned apply endpoint should return an ok envelope.');
   $written = is_array($applyPayload['data']['written'] ?? null) ? $applyPayload['data']['written'] : [];
   assertTrue(
      in_array('src/Demo/Controller/Base/AppliedUserControllerBase.php', $written, true)
      && in_array('src/Demo/Controller/AppliedUserController.php', $written, true),
      'Versioned apply endpoint should write base and user controller files.'
   );

   $userControllerPath = $tmpRoot . '/src/Demo/Controller/AppliedUserController.php';
   file_put_contents($userControllerPath, (string) file_get_contents($userControllerPath) . "\n// custom change\n");

   $regenRequest = new Request(
      'POST',
      '/__dev/tooling/api/v1/generate/apply',
      ['content-type' => 'application/json'],
      [],
      (string) json_encode([
         'generator' => 'controller',
         'name' => 'AppliedUser',
         'module' => 'Demo',
         'overwrite' => true,
      ], JSON_UNESCAPED_SLASHES)
   );
   $regenResponse = $controller->handle($ctx, $regenRequest);
   assertTrue($regenResponse->getStatus() === 200, 'Regeneration endpoint should return 200.');

   $regenPayload = json_decode($regenResponse->getBody(), true);
   assertTrue(is_array($regenPayload), 'Regeneration endpoint should return JSON payload.');
   $regenWritten = is_array($regenPayload['data']['written'] ?? null) ? $regenPayload['data']['written'] : [];

   assertTrue(
      !in_array('src/Demo/Controller/AppliedUserController.php', $regenWritten, true),
      'Regeneration should preserve user controller wrapper file.'
   );
   assertTrue(
      str_contains((string) file_get_contents($userControllerPath), '// custom change'),
      'Regeneration should not remove custom edits from user controller wrapper.'
   );

   $appKeyRequest = new Request(
      'POST',
      '/__dev/tooling/api/v1/app-key/generate',
      ['content-type' => 'application/json'],
      [],
      (string) json_encode(['show' => true], JSON_UNESCAPED_SLASHES)
   );
   $appKeyResponse = $controller->handle($ctx, $appKeyRequest);
   assertTrue($appKeyResponse->getStatus() === 200, 'APP key endpoint should return 200.');

   $appKeyPayload = json_decode($appKeyResponse->getBody(), true);
   assertTrue(is_array($appKeyPayload), 'APP key endpoint should return JSON payload.');
   assertTrue(($appKeyPayload['status'] ?? null) === 'ok', 'APP key endpoint should return an ok envelope.');
   $appKeyData = is_array($appKeyPayload['data'] ?? null) ? $appKeyPayload['data'] : [];
   assertTrue(is_string($appKeyData['key'] ?? null), 'APP key endpoint should return generated key when requested.');
   assertTrue(str_starts_with((string) ($appKeyData['key'] ?? ''), 'base64:'), 'Generated APP key should have base64 prefix.');

   $envPath = $tmpRoot . '/.env';
   assertTrue(is_file($envPath), 'APP key endpoint should create .env when missing.');
   $envContents = (string) file_get_contents($envPath);
   assertTrue(
      str_contains($envContents, 'APP_KEY=' . $appKeyData['key']),
      'APP key endpoint should persist generated key to .env.'
   );
}

/**
 * Handle run diff generation correctness.
 *
 * @return void
 */
function runDiffGenerationCorrectness(): void
{
   $diffBuilder = new UnifiedDiffBuilder();
   $diff = $diffBuilder->build("alpha\nbeta\n", "alpha\ngamma\n", 'a/sample.txt', 'b/sample.txt');

   assertTrue(str_contains($diff, '--- a/sample.txt'), 'Diff must include old file header.');
   assertTrue(str_contains($diff, '+++ b/sample.txt'), 'Diff must include new file header.');
   assertTrue(str_contains($diff, '-beta'), 'Diff must include removed line marker.');
   assertTrue(str_contains($diff, '+gamma'), 'Diff must include added line marker.');
}

/**
 * Handle run dependency graph accuracy.
 *
 * @return void
 */
function runDependencyGraphAccuracy(): void
{
   $root = '/tmp/celeris-phase10-graph-' . bin2hex(random_bytes(6));
   @mkdir($root . '/Alpha', 0777, true);
   @mkdir($root . '/Beta', 0777, true);

   file_put_contents($root . '/Alpha/One.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Celeris\Framework\Alpha;

use Celeris\Framework\Beta\Two;

final class One
{
   public function __construct(private Two $two)
   {
   }
}
PHP);

   file_put_contents($root . '/Beta/Two.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Celeris\Framework\Beta;

final class Two
{
}
PHP);

   $builder = new DependencyGraphBuilder($root);
   $map = $builder->moduleDependencyMap();

   assertTrue(isset($map['Alpha']), 'Dependency map should include Alpha module.');
   assertTrue(isset($map['Beta']), 'Dependency map should include Beta module.');
   assertTrue($map['Alpha'] === ['Beta'], 'Alpha module should depend on Beta module.');
   assertTrue($map['Beta'] === [], 'Beta module should have no dependencies in this fixture.');
}

/**
 * Handle run cli app key command.
 *
 * @return void
 */
function runCliAppKeyCommand(): void
{
   $root = '/tmp/celeris-phase10-cli-' . bin2hex(random_bytes(6));
   @mkdir($root, 0777, true);

   $app = new ToolingCliApplication(
      new GeneratorEngine(),
      new DependencyGraphBuilder(__DIR__ . '/../src'),
      new ArchitectureDecisionValidator(),
      $root,
      'App'
   );

   $exitCode = $app->run(['celeris', 'app-key', '--show']);
   assertTrue($exitCode === 0, 'CLI app-key command should return zero.');

   $envPath = $root . '/.env';
   assertTrue(is_file($envPath), 'CLI app-key command should write .env.');
   $contents = (string) file_get_contents($envPath);
   assertTrue(str_contains($contents, 'APP_KEY=base64:'), 'CLI app-key command should write APP_KEY.');
}

/**
 * @return void
 */
function runCliSchemaConnectionsCommand(): void
{
   $root = '/tmp/celeris-phase10-cli-schema-' . bin2hex(random_bytes(6));
   @mkdir($root . '/config', 0777, true);

   file_put_contents($root . '/config/app.php', <<<'PHP'
<?php
return [
   'name' => 'CLI Schema Test',
   'env' => 'development',
];
PHP
);
   file_put_contents($root . '/config/database.php', <<<'PHP'
<?php
return [
   'default' => 'sqlite',
   'connections' => [
      'sqlite' => [
         'driver' => 'sqlite',
         'path' => ':memory:',
      ],
   ],
];
PHP
);

   $app = new ToolingCliApplication(
      new GeneratorEngine(),
      new DependencyGraphBuilder(__DIR__ . '/../src'),
      new ArchitectureDecisionValidator(),
      $root,
      'App'
   );

   $exitCode = $app->run(['celeris', 'schema:connections', '--json']);
   assertTrue($exitCode === 0, 'CLI schema:connections command should return zero.');
}

$checks = [
   'UxTesting' => 'runUxTesting',
   'DiffGenerationCorrectness' => 'runDiffGenerationCorrectness',
   'DependencyGraphAccuracy' => 'runDependencyGraphAccuracy',
   'CliAppKeyCommand' => 'runCliAppKeyCommand',
   'CliSchemaConnectionsCommand' => 'runCliSchemaConnectionsCommand',
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
