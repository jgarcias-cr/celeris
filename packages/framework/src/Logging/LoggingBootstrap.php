<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

use Celeris\Framework\Config\ConfigRepository;

/**
 * Build framework logger instances from runtime config and project context.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class LoggingBootstrap
{
   public static function fromConfig(ConfigRepository $config, string $projectRoot): LoggerInterface
   {
      $minimumLevel = (string) $config->get(
         'logging.level',
         (bool) $config->get('app.debug', false) ? LogLevel::DEBUG : LogLevel::INFO
      );
      $root = rtrim($projectRoot, '/\\');
      if ($root === '') {
         return new NullLogger();
      }

      $path = $root . '/var/log/app.log';
      return new FileLogger($path, $minimumLevel);
   }
}

