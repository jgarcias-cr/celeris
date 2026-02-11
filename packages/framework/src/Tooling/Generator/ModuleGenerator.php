<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Purpose: implement module generator behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when module generator functionality is required.
 */
final class ModuleGenerator implements GeneratorInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'module';
   }

   /**
    * Handle description.
    *
    * @return string
    */
   public function description(): string
   {
      return 'Generates a composable module with a service provider and routes scaffold.';
   }

   /**
    * @return array<int, GeneratedFileDraft>
    */
   public function generate(GenerationRequest $request): array
   {
      $module = $this->studly($request->name());
      $namespaceRoot = trim($request->namespaceRoot(), '\\');

      $providerClass = $module . 'ServiceProvider';
      $providerNamespace = $namespaceRoot . '\\' . $module;

      $providerPath = 'src/' . $module . '/' . $providerClass . '.php';
      $providerContents = <<<PHP
<?php

declare(strict_types=1);

namespace {$providerNamespace};

use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;

final class {$providerClass} implements ServiceProviderInterface
{
   public function register(ServiceRegistry \$services): void
   {
      // Register module services here.
   }
}
PHP;

      $controllerPath = 'src/' . $module . '/Controller/' . $module . 'HealthController.php';
      $controllerNamespace = $namespaceRoot . '\\' . $module . '\\Controller';
      $routePath = '/' . strtolower($module) . '/health';
      $controllerContents = <<<PHP
<?php

declare(strict_types=1);

namespace {$controllerNamespace};

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;

final class {$module}HealthController
{
   #[Route(methods: ['GET'], path: '{$routePath}', summary: '{$module} health check')]
   public function __invoke(RequestContext \$ctx, Request \$request): Response
   {
      return new Response(200, ['content-type' => 'application/json; charset=utf-8'], '{"module":"{$module}","status":"ok"}');
   }
}
PHP;

      return [
         new GeneratedFileDraft($providerPath, $providerContents . "\n"),
         new GeneratedFileDraft($controllerPath, $controllerContents . "\n"),
      ];
   }

   /**
    * Handle studly.
    *
    * @param string $value
    * @return string
    */
   private function studly(string $value): string
   {
      $clean = preg_replace('/[^A-Za-z0-9]+/', ' ', $value) ?? '';
      $parts = array_filter(explode(' ', trim($clean)), static fn (string $part): bool => $part !== '');
      $studly = implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));
      return $studly !== '' ? $studly : 'Module';
   }
}



