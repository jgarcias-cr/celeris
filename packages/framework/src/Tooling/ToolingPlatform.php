<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling;

use Celeris\Framework\Tooling\Architecture\ArchitectureDecisionValidator;
use Celeris\Framework\Tooling\Cli\ToolingCliApplication;
use Celeris\Framework\Routing\RouteCollector;
use Celeris\Framework\Tooling\Generator\GeneratorEngine;
use Celeris\Framework\Tooling\Graph\DependencyGraphBuilder;
use Celeris\Framework\Tooling\Web\DeveloperUiController;

/**
 * Purpose: implement tooling platform behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when tooling platform functionality is required.
 */
final class ToolingPlatform
{
   private GeneratorEngine $generatorEngine;
   private DependencyGraphBuilder $dependencyGraphBuilder;
   private ArchitectureDecisionValidator $architectureValidator;

   /**
    * Create a new instance.
    *
    * @param string $projectRoot
    * @param string $frameworkSourcePath
    * @param string $namespaceRoot
    * @param string $frameworkNamespace
    * @return mixed
    */
   public function __construct(
      private string $projectRoot,
      private string $frameworkSourcePath,
      private string $namespaceRoot = 'App',
      private string $frameworkNamespace = 'Celeris\\Framework\\',
   ) {
      $this->generatorEngine = new GeneratorEngine();
      $this->dependencyGraphBuilder = new DependencyGraphBuilder($this->frameworkSourcePath, $this->frameworkNamespace);
      $this->architectureValidator = new ArchitectureDecisionValidator();
   }

   /**
    * Create an instance from project root.
    *
    * @param string $projectRoot
    * @return self
    */
   public static function fromProjectRoot(string $projectRoot): self
   {
      $root = rtrim($projectRoot, '/');

      $candidates = [
         $root . '/packages/framework/src',
         $root . '/vendor/celeris/framework/src',
         dirname($root) . '/framework/src',
      ];

      foreach ($candidates as $candidate) {
         if (is_dir($candidate)) {
            return new self($root, $candidate);
         }
      }

      return new self($root, $root . '/packages/framework/src');
   }

   public function mountWebUiRoutes(RouteCollector $routes, string $routePrefix = '/__dev/tooling'): DeveloperUiController
   {
      $webUi = $this->webUi($routePrefix);
      $base = rtrim($routePrefix, '/');
      if ($base === '') {
         $base = '/';
      }

      $routes->get($base, $webUi);
      $routes->get($base . '/graph', $webUi);
      $routes->get($base . '/validate', $webUi);
      $routes->get($base . '/generate/preview', $webUi);
      $routes->get($base . '/generate/apply', $webUi);

      $routes->get($base . '/api/v1', $webUi);
      $routes->get($base . '/api/v1/summary', $webUi);
      $routes->get($base . '/api/v1/health', $webUi);
      $routes->get($base . '/api/v1/graph', $webUi);
      $routes->get($base . '/api/v1/validate', $webUi);
      $routes->get($base . '/api/v1/generators', $webUi);
      $routes->get($base . '/api/v1/generate/preview', $webUi);
      $routes->post($base . '/api/v1/generate/preview', $webUi);
      $routes->post($base . '/api/v1/generate/apply', $webUi);
      $routes->get($base . '/api/v1/schema/connections', $webUi);
      $routes->get($base . '/api/v1/schema/tables', $webUi);
      $routes->get($base . '/api/v1/schema/tables/{table}', $webUi);
      $routes->post($base . '/api/v1/scaffold/preview', $webUi);
      $routes->post($base . '/api/v1/scaffold/apply', $webUi);
      $routes->get($base . '/api/v1/compat/breaking-changes', $webUi);
      $routes->post($base . '/api/v1/compat/baseline/save', $webUi);

      return $webUi;
   }

   /**
    * Handle generator engine.
    *
    * @return GeneratorEngine
    */
   public function generatorEngine(): GeneratorEngine
   {
      return $this->generatorEngine;
   }

   /**
    * Handle dependency graph builder.
    *
    * @return DependencyGraphBuilder
    */
   public function dependencyGraphBuilder(): DependencyGraphBuilder
   {
      return $this->dependencyGraphBuilder;
   }

   /**
    * Handle architecture validator.
    *
    * @return ArchitectureDecisionValidator
    */
   public function architectureValidator(): ArchitectureDecisionValidator
   {
      return $this->architectureValidator;
   }

   /**
    * Handle cli.
    *
    * @return ToolingCliApplication
    */
   public function cli(): ToolingCliApplication
   {
      return new ToolingCliApplication(
         $this->generatorEngine,
         $this->dependencyGraphBuilder,
         $this->architectureValidator,
         $this->projectRoot,
         $this->namespaceRoot,
      );
   }

   /**
    * Handle web ui.
    *
    * @param string $routePrefix
    * @return DeveloperUiController
    */
   public function webUi(string $routePrefix = '/__dev/tooling'): DeveloperUiController
   {
      return new DeveloperUiController(
         $this->generatorEngine,
         $this->dependencyGraphBuilder,
         $this->architectureValidator,
         $this->projectRoot,
         $routePrefix,
         $this->namespaceRoot,
      );
   }
}
