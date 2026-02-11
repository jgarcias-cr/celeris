<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Diff\UnifiedDiffBuilder;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
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
      'App'
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
   assertTrue(
      str_contains((string) ($payload['files'][0]['diff'] ?? ''), '+++ b/src/Demo/Controller/PreviewUserController.php'),
      'Generator preview payload should include a visual diff.'
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

$checks = [
   'UxTesting' => 'runUxTesting',
   'DiffGenerationCorrectness' => 'runDiffGenerationCorrectness',
   'DependencyGraphAccuracy' => 'runDependencyGraphAccuracy',
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

