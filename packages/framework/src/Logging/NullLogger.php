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
   /**
    * Log an emergency-level message.
    */
   public function emergency(string $message, array $context = []): void
   {
      $this->log(LogLevel::EMERGENCY, $message, $context);
   }

   /**
    * Log an alert-level message.
    */
   public function alert(string $message, array $context = []): void
   {
      $this->log(LogLevel::ALERT, $message, $context);
   }

   /**
    * Log a critical-level message.
    */
   public function critical(string $message, array $context = []): void
   {
      $this->log(LogLevel::CRITICAL, $message, $context);
   }

   /**
    * Log an error-level message.
    */
   public function error(string $message, array $context = []): void
   {
      $this->log(LogLevel::ERROR, $message, $context);
   }

   /**
    * Log a warning-level message.
    */
   public function warning(string $message, array $context = []): void
   {
      $this->log(LogLevel::WARNING, $message, $context);
   }

   /**
    * Log a notice-level message.
    */
   public function notice(string $message, array $context = []): void
   {
      $this->log(LogLevel::NOTICE, $message, $context);
   }

   /**
    * Log an info-level message.
    */
   public function info(string $message, array $context = []): void
   {
      $this->log(LogLevel::INFO, $message, $context);
   }

   /**
    * Log a debug-level message.
    */
   public function debug(string $message, array $context = []): void
   {
      $this->log(LogLevel::DEBUG, $message, $context);
   }

   /**
    * Discard the message while honoring the logger interface contract.
    */
   public function log(string $level, string $message, array $context = []): void
   {
   }
}

