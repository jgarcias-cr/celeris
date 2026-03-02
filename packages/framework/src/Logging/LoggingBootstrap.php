<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

use Celeris\Framework\Config\ConfigRepository;

/**
 * Purpose: build framework logger instances from runtime config and project context.
 * How: resolves a fixed project log path and creates a file logger with level filtering.
 * Used in framework: invoked by Kernel during container rebuild and boot.
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

