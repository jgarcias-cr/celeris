<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Routing;

use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Routing\RouteDefinition;
use Celeris\Framework\Tooling\ToolingException;
use Throwable;

/**
 * Loads application routes and normalizes them into tooling-friendly rows.
 */
final class ProjectRouteInspector
{
   /**
    * @param array<int, RouteDefinition>|null $routeDefinitions
    * @return array<int, array{method:string,uri:string,action:string,middleware:array<int, string>}>
    */
   public function inspect(string $projectRoot, ?array $routeDefinitions = null): array
   {
      $definitions = $routeDefinitions ?? $this->loadRouteDefinitions($projectRoot);
      $rows = [];

      foreach ($definitions as $route) {
         if (!$route instanceof RouteDefinition) {
            continue;
         }

         foreach ($route->methods() as $method) {
            $uri = $route->path();
            if (str_starts_with($uri, '/__dev')) {
               continue;
            }

            $rows[] = [
               'method' => strtoupper(trim((string) $method)),
               'uri' => $uri,
               'action' => $this->handlerToAction($route->handler()),
               'middleware' => $this->normalizeMiddleware($route->middleware()),
            ];
         }
      }

      usort($rows, static function (array $a, array $b): int {
         $uriComparison = strcmp($a['uri'], $b['uri']);
         if ($uriComparison !== 0) {
            return $uriComparison;
         }

         $methodComparison = strcmp($a['method'], $b['method']);
         if ($methodComparison !== 0) {
            return $methodComparison;
         }

         return strcmp($a['action'], $b['action']);
      });

      return $rows;
   }

   /**
    * @return array<int, RouteDefinition>
    */
   private function loadRouteDefinitions(string $projectRoot): array
   {
      $indexPath = rtrim($projectRoot, '/\\') . '/public/index.php';
      if (!is_file($indexPath)) {
         throw new ToolingException(sprintf('Route bootstrap file not found at "%s".', $indexPath));
      }

      $source = @file_get_contents($indexPath);
      if (!is_string($source) || $source === '') {
         throw new ToolingException(sprintf('Unable to read route bootstrap file "%s".', $indexPath));
      }

      $patched = $this->stripWorkerRunner($source);
      try {
         $scratch = dirname($indexPath) . '/.__celeris_route_scan_' . bin2hex(random_bytes(6)) . '.php';
      } catch (Throwable $exception) {
         throw new ToolingException('Unable to allocate route scan workspace: ' . $exception->getMessage(), 0, $exception);
      }
      if (@file_put_contents($scratch, $patched) === false) {
         throw new ToolingException(sprintf('Unable to create route scan file "%s".', $scratch));
      }

      try {
         $kernel = $this->loadKernelFromScratchFile($scratch);
         if (!$kernel instanceof Kernel) {
            throw new ToolingException('Unable to resolve Kernel instance from app bootstrap.');
         }

         $routes = $kernel->getRouter()->allRoutes();
         $kernel->shutdown();
      } finally {
         @unlink($scratch);
      }

      return $routes;
   }

   /**
    * Strip worker-runner bootstrap code from the generated scratch script.
    */
   private function stripWorkerRunner(string $source): string
   {
      $result = preg_replace(
         '/\\$runner\\s*=\\s*new\\s+(?:\\\\?[A-Za-z_\\\\]*WorkerRunner)\\s*\\(.*?\\)\\s*;\\s*\\$runner\\s*->\\s*run\\s*\\(\\s*\\)\\s*;?/s',
         '',
         $source,
      );
      if (!is_string($result)) {
         throw new ToolingException('Unable to prepare bootstrap script for route inspection.');
      }

      $result = preg_replace(
         '/\\(\\s*new\\s+(?:\\\\?[A-Za-z_\\\\]*WorkerRunner)\\s*\\(.*?\\)\\s*\\)\\s*->\\s*run\\s*\\(\\s*\\)\\s*;?/s',
         '',
         $result,
      );
      if (!is_string($result)) {
         throw new ToolingException('Unable to prepare bootstrap script for route inspection.');
      }

      return $result;
   }

   /**
    * Load a kernel instance from the generated scratch bootstrap file.
    */
   private function loadKernelFromScratchFile(string $scratchPath): mixed
   {
      try {
         return (static function (string $file): mixed {
            $kernel = null;
            ob_start();
            try {
               include $file;
            } finally {
               ob_end_clean();
            }

            return $kernel;
         })($scratchPath);
      } catch (Throwable $exception) {
         throw new ToolingException('Route inspection failed: ' . $exception->getMessage(), 0, $exception);
      }
   }

   /**
    * @param array<int, string> $middleware
    * @return array<int, string>
    */
   private function normalizeMiddleware(array $middleware): array
   {
      $normalized = [];
      foreach ($middleware as $entry) {
         $clean = trim((string) $entry);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      return array_values(array_unique($normalized));
   }

   /**
    * Convert a route handler into a readable action label.
    */
   private function handlerToAction(mixed $handler): string
   {
      if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0]) && is_string($handler[1])) {
         return $handler[0] . '@' . $handler[1];
      }

      if (is_string($handler)) {
         return trim($handler) !== '' ? trim($handler) : 'Closure';
      }

      if ($handler instanceof \Closure) {
         return 'Closure';
      }

      if (is_object($handler)) {
         return $handler::class;
      }

      return 'Closure';
   }
}
