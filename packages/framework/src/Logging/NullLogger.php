<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

/**
 * Purpose: provide a no-op logger for environments where writing logs is disabled.
 * How: accepts log calls and intentionally performs no side effects.
 * Used in framework: safe fallback when a concrete logger cannot be created.
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

