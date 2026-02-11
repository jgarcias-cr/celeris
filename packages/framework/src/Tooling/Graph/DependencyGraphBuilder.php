<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Graph;

use Celeris\Framework\Container\ServiceRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Purpose: compose dependency graph builder output from incremental inputs.
 * How: accumulates options explicitly and emits a finalized immutable result.
 * Used in framework: invoked by tooling components when dependency graph builder functionality is required.
 */
final class DependencyGraphBuilder
{
   /**
    * Create a new instance.
    *
    * @param string $sourcePath
    * @param string $rootNamespace
    * @return mixed
    */
   public function __construct(
      private string $sourcePath,
      private string $rootNamespace = 'Celeris\\Framework\\',
   ) {
   }

   /**
    * Handle build module graph.
    *
    * @return DependencyGraph
    */
   public function buildModuleGraph(): DependencyGraph
   {
      $graph = new DependencyGraph();

      foreach ($this->phpFiles($this->sourcePath) as $path) {
         $contents = (string) file_get_contents($path);
         $module = $this->moduleFromNamespace($this->namespaceOf($contents));
         if ($module === null) {
            continue;
         }

         $graph->addNode($module, 'module');

         foreach ($this->moduleImports($contents) as $targetModule) {
            $graph->addNode($targetModule, 'module');
            if ($targetModule !== $module) {
               $graph->addEdge($module, $targetModule, 'module_dep');
            }
         }
      }

      return $graph;
   }

   /**
    * Handle build service graph.
    *
    * @param ServiceRegistry $registry
    * @return DependencyGraph
    */
   public function buildServiceGraph(ServiceRegistry $registry): DependencyGraph
   {
      $graph = new DependencyGraph();

      foreach ($registry->all() as $id => $definition) {
         $graph->addNode($id, 'service');
         foreach ($definition->getDependencies() as $dependencyId) {
            $graph->addNode($dependencyId, 'service');
            $graph->addEdge($id, $dependencyId, 'service_dep');
         }
      }

      return $graph;
   }

   /**
    * @return array<string, array<int, string>>
    */
   public function moduleDependencyMap(): array
   {
      $graph = $this->buildModuleGraph();
      $map = [];

      foreach ($graph->nodes() as $node) {
         if ($node['type'] !== 'module') {
            continue;
         }
         $map[$node['id']] = [];
      }

      foreach ($graph->edges() as $edge) {
         if ($edge->type() !== 'module_dep') {
            continue;
         }
         $map[$edge->from()][] = $edge->to();
      }

      foreach ($map as $module => $deps) {
         $deps = array_values(array_unique($deps));
         sort($deps);
         $map[$module] = $deps;
      }

      ksort($map);
      return $map;
   }

   /**
    * @return array<int, string>
    */
   private function moduleImports(string $contents): array
   {
      preg_match_all('/^use\s+([^;]+);/m', $contents, $matches);
      $imports = [];

      foreach ($matches[1] ?? [] as $rawImport) {
         foreach ($this->expandUseStatement((string) $rawImport) as $import) {
            $module = $this->moduleFromClass($import);
            if ($module !== null) {
               $imports[] = $module;
            }
         }
      }

      preg_match_all('/\\\\Celeris\\\\Framework\\\\([A-Za-z0-9_\\\\]+)/', $contents, $fqcnMatches);
      foreach ($fqcnMatches[1] ?? [] as $tail) {
         $module = $this->moduleFromClass('Celeris\\Framework\\' . trim((string) $tail, '\\'));
         if ($module !== null) {
            $imports[] = $module;
         }
      }

      $imports = array_values(array_unique($imports));
      sort($imports);
      return $imports;
   }

   /**
    * @return array<int, string>
    */
   private function expandUseStatement(string $statement): array
   {
      $trimmed = trim($statement);
      if ($trimmed === '') {
         return [];
      }

      $imports = [];
      if (str_contains($trimmed, '{') && str_contains($trimmed, '}')) {
         $prefix = substr($trimmed, 0, (int) strpos($trimmed, '{'));
         $inside = substr($trimmed, (int) strpos($trimmed, '{') + 1);
         $inside = substr($inside, 0, (int) strpos($inside, '}'));

         foreach (explode(',', $inside) as $part) {
            $name = trim($part);
            if ($name === '') {
               continue;
            }
            $imports[] = trim($prefix, '\\') . '\\' . trim(explode(' as ', $name)[0], '\\ ');
         }

         return $imports;
      }

      $imports[] = trim(explode(' as ', $trimmed)[0], '\\ ');
      return $imports;
   }

   /**
    * Handle namespace of.
    *
    * @param string $contents
    * @return ?string
    */
   private function namespaceOf(string $contents): ?string
   {
      if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches) !== 1) {
         return null;
      }

      return trim((string) ($matches[1] ?? ''));
   }

   /**
    * Handle module from namespace.
    *
    * @param ?string $namespace
    * @return ?string
    */
   private function moduleFromNamespace(?string $namespace): ?string
   {
      if (!is_string($namespace) || $namespace === '') {
         return null;
      }

      if (!str_starts_with($namespace . '\\', $this->rootNamespace)) {
         return null;
      }

      $tail = substr($namespace . '\\', strlen($this->rootNamespace));
      $tail = trim($tail, '\\');
      if ($tail === '') {
         return null;
      }

      return explode('\\', $tail)[0] ?: null;
   }

   /**
    * Handle module from class.
    *
    * @param string $class
    * @return ?string
    */
   private function moduleFromClass(string $class): ?string
   {
      $normalized = ltrim(trim($class), '\\');
      if (!str_starts_with($normalized . '\\', $this->rootNamespace)) {
         return null;
      }

      $tail = substr($normalized . '\\', strlen($this->rootNamespace));
      $tail = trim($tail, '\\');
      if ($tail === '') {
         return null;
      }

      return explode('\\', $tail)[0] ?: null;
   }

   /**
    * @return array<int, string>
    */
   private function phpFiles(string $root): array
   {
      if (!is_dir($root)) {
         return [];
      }

      $files = [];
      $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
      foreach ($iterator as $file) {
         if (!$file->isFile()) {
            continue;
         }

         $path = $file->getPathname();
         if (str_ends_with($path, '.php')) {
            $files[] = $path;
         }
      }

      sort($files);
      return $files;
   }
}



