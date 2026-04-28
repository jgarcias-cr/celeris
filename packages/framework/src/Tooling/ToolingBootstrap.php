<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling;

use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Tooling\Web\DeveloperUiController;

/**
 * Provides a small bootstrap integration point for optional tooling features.
 */
final class ToolingBootstrap
{
   /**
    * Mount the tooling web UI only when the current environment allows it.
    */
   public static function mountIfEnabled(
      Kernel $kernel,
      string $projectRoot,
      string $routePrefix = '/__dev/tooling',
      string $envKey = 'TOOLING_WEB_ENABLED',
   ): ?DeveloperUiController {
      if (!self::envFlag($envKey)) {
         return null;
      }

      $tooling = ToolingPlatform::fromProjectRoot($projectRoot);
      return $tooling->mountWebUiRoutes($kernel->routes(), $routePrefix);
   }

   /**
    * Read a nullable boolean flag from the environment.
    */
   private static function envFlag(string $key): bool
   {
      $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
      if ($value === false || $value === null) {
         $value = $_ENV['TOOLING_ENABLED'] ?? $_SERVER['TOOLING_ENABLED'] ?? getenv('TOOLING_ENABLED');
      }

      return filter_var($value, FILTER_VALIDATE_BOOLEAN) === true;
   }
}
