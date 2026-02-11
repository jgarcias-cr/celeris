<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Purpose: implement controller generator behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when controller generator functionality is required.
 */
final class ControllerGenerator implements GeneratorInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'controller';
   }

   /**
    * Handle description.
    *
    * @return string
    */
   public function description(): string
   {
      return 'Generates an attribute-routed HTTP controller class.';
   }

   /**
    * @return array<int, GeneratedFileDraft>
    */
   public function generate(GenerationRequest $request): array
   {
      $module = $this->studly($request->module());
      $name = $this->studly($request->name());
      $className = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
      $methodName = 'index';

      $routePrefix = trim((string) $request->option('route_prefix', '/' . strtolower($name)), '/');
      $routePath = '/' . ($routePrefix !== '' ? $routePrefix : strtolower($name));

      $namespace = trim($request->namespaceRoot(), '\\') . '\\' . $module . '\\Controller';
      $path = 'src/' . $module . '/Controller/' . $className . '.php';

      $contents = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;

final class {$className}
{
   #[Route(methods: ['GET'], path: '{$routePath}', summary: '{$name} endpoint')]
   public function {$methodName}(RequestContext \$ctx, Request \$request): Response
   {
      return new Response(200, ['content-type' => 'application/json; charset=utf-8'], '{"ok":true}');
   }
}
PHP;

      return [new GeneratedFileDraft($path, $contents . "\n")];
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
      return $studly !== '' ? $studly : 'Generated';
   }
}



