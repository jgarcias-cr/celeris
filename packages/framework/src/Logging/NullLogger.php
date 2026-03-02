<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

/**
 * Provide a no-op logger for environments where writing logs is disabled.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class NullLogger implements LoggerInterface
{
   public function emergency(string $message, array $context = []): void
   {
      $this->log(LogLevel::EMERGENCY, $message, $context);
   }

   public function alert(string $message, array $context = []): void
   {
      $this->log(LogLevel::ALERT, $message, $context);
   }

   public function critical(string $message, array $context = []): void
   {
      $this->log(LogLevel::CRITICAL, $message, $context);
   }

   public function error(string $message, array $context = []): void
   {
      $this->log(LogLevel::ERROR, $message, $context);
   }

   public function warning(string $message, array $context = []): void
   {
      $this->log(LogLevel::WARNING, $message, $context);
   }

   public function notice(string $message, array $context = []): void
   {
      $this->log(LogLevel::NOTICE, $message, $context);
   }

   public function info(string $message, array $context = []): void
   {
      $this->log(LogLevel::INFO, $message, $context);
   }

   public function debug(string $message, array $context = []): void
   {
      $this->log(LogLevel::DEBUG, $message, $context);
   }

   public function log(string $level, string $message, array $context = []): void
   {
   }
}

