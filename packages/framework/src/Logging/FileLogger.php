<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

use JsonSerializable;
use Throwable;

/**
 * Append structured log records to a fixed file path.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class FileLogger implements LoggerInterface
{
   /**
    * @var array<string, int>
    */
   private array $severity;
   private int $minimum;
   private bool $directoryPrepared = false;

   /**
    * Create a file logger with the target path and minimum log level.
    */
   public function __construct(
      private string $path,
      string $minimumLevel = LogLevel::INFO,
   ) {
      $this->severity = LogLevel::severityMap();
      $this->minimum = $this->severity[LogLevel::normalize($minimumLevel)] ?? $this->severity[LogLevel::INFO];
   }

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
    * Write a normalized log record when it meets the configured threshold.
    */
   public function log(string $level, string $message, array $context = []): void
   {
      $normalized = LogLevel::normalize($level);
      $weight = $this->severity[$normalized] ?? $this->severity[LogLevel::INFO];
      if ($weight < $this->minimum) {
         return;
      }

      $this->prepareDirectory();
      $record = [
         'timestamp' => gmdate('c'),
         'level' => $normalized,
         'message' => $message,
         'context' => $this->normalizeValue($context),
      ];

      $payload = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($payload)) {
         $payload = '{"timestamp":"' . gmdate('c') . '","level":"error","message":"Unable to encode log record.","context":{}}';
      }

      @file_put_contents($this->path, $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
   }

   /**
    * Create the target log directory the first time it is needed.
    */
   private function prepareDirectory(): void
   {
      if ($this->directoryPrepared) {
         return;
      }

      $dir = dirname($this->path);
      if (!is_dir($dir)) {
         @mkdir($dir, 0775, true);
      }

      $this->directoryPrepared = true;
   }

   /**
    * Normalize context values into JSON-safe data for log records.
    */
   private function normalizeValue(mixed $value): mixed
   {
      if (is_array($value)) {
         $normalized = [];
         foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->normalizeValue($item);
         }
         return $normalized;
      }

      if (is_scalar($value) || $value === null) {
         return $value;
      }

      if ($value instanceof Throwable) {
         return [
            'type' => $value::class,
            'message' => $value->getMessage(),
            'file' => $value->getFile(),
            'line' => $value->getLine(),
         ];
      }

      if ($value instanceof JsonSerializable) {
         return $this->normalizeValue($value->jsonSerialize());
      }

      if (is_object($value)) {
         return ['type' => $value::class];
      }

      if (is_resource($value)) {
         return ['type' => 'resource:' . get_resource_type($value)];
      }

      return (string) $value;
   }
}

