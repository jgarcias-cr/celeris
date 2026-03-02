<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Architecture;

use Celeris\Framework\Tooling\Graph\DependencyGraph;

/**
 * Implement architecture decision validator behavior for the Tooling subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ArchitectureDecisionValidator
{
   /**
    * @var array<string, array<int, string>>
    */
   private array $forbiddenDependencies;

   /**
    * @param array<string, array<int, string>> $forbiddenDependencies
    */
   public function __construct(array $forbiddenDependencies = [])
   {
      $this->forbiddenDependencies = $forbiddenDependencies !== []
         ? $forbiddenDependencies
         : [
            'Domain' => ['Http', 'Runtime', 'Routing', 'Security', 'Tooling'],
            'Runtime' => ['Tooling'],
         ];
   }

   /**
    * Handle validate.
    *
    * @param DependencyGraph $graph
    * @return ArchitectureValidationReport
    */
   public function validate(DependencyGraph $graph): ArchitectureValidationReport
   {
      $report = new ArchitectureValidationReport();
      $adjacency = $this->moduleAdjacency($graph);

      $this->validateForbiddenDependencies($adjacency, $report);
      $this->validateNoCycles($adjacency, $report);

      return $report;
   }

   /**
    * @param array<string, array<int, string>> $adjacency
    */
   private function validateForbiddenDependencies(array $adjacency, ArchitectureValidationReport $report): void
   {
      foreach ($this->forbiddenDependencies as $module => $forbiddenTargets) {
         $targets = $adjacency[$module] ?? [];
         foreach ($targets as $target) {
            if (in_array($target, $forbiddenTargets, true)) {
               $report->add(new ArchitectureViolation(
                  'forbidden_dependency',
                  sprintf('Module "%s" must not depend on "%s".', $module, $target)
               ));
            }
         }
      }
   }

   /**
    * @param array<string, array<int, string>> $adjacency
    */
   private function validateNoCycles(array $adjacency, ArchitectureValidationReport $report): void
   {
      /** @var array<string, bool> $visiting */
      $visiting = [];
      /** @var array<string, bool> $visited */
      $visited = [];
      /** @var array<int, string> $stack */
      $stack = [];

      $visit = function (string $module) use (&$visit, &$visiting, &$visited, &$stack, $adjacency, $report): void {
         if (($visiting[$module] ?? false) === true) {
            $index = array_search($module, $stack, true);
            $cycle = $index === false ? [$module] : array_slice($stack, (int) $index);
            $cycle[] = $module;

            $report->add(new ArchitectureViolation(
               'module_cycle',
               'Module cycle detected: ' . implode(' -> ', $cycle)
            ));
            return;
         }

         if (($visited[$module] ?? false) === true) {
            return;
         }

         $visiting[$module] = true;
         $stack[] = $module;

         foreach ($adjacency[$module] ?? [] as $target) {
            $visit($target);
         }

         array_pop($stack);
         $visiting[$module] = false;
         $visited[$module] = true;
      };

      $modules = array_keys($adjacency);
      sort($modules);
      foreach ($modules as $module) {
         $visit($module);
      }
   }

   /**
    * @return array<string, array<int, string>>
    */
   private function moduleAdjacency(DependencyGraph $graph): array
   {
      $adjacency = [];
      foreach ($graph->nodes() as $node) {
         if (($node['type'] ?? null) === 'module') {
            $adjacency[$node['id']] = [];
         }
      }

      foreach ($graph->edges() as $edge) {
         if ($edge->type() !== 'module_dep') {
            continue;
         }

         $adjacency[$edge->from()][] = $edge->to();
      }

      foreach ($adjacency as $module => $targets) {
         $targets = array_values(array_unique($targets));
         sort($targets);
         $adjacency[$module] = $targets;
      }

      return $adjacency;
   }
}



