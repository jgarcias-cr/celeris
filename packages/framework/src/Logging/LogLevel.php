<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

/**
 * Centralize supported log levels and ordering for filtering.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class LogLevel
{
   public const EMERGENCY = 'emergency';
   public const ALERT = 'alert';
   public const CRITICAL = 'critical';
   public const ERROR = 'error';
   public const WARNING = 'warning';
   public const NOTICE = 'notice';
   public const INFO = 'info';
   public const DEBUG = 'debug';

   /**
    * @return array<string, int>
    */
   public static function severityMap(): array
   {
      return [
         self::EMERGENCY => 800,
         self::ALERT => 700,
         self::CRITICAL => 600,
         self::ERROR => 500,
         self::WARNING => 400,
         self::NOTICE => 300,
         self::INFO => 200,
         self::DEBUG => 100,
      ];
   }

   /**
    * Normalize an arbitrary log level string to a supported constant.
    */
   public static function normalize(string $level): string
   {
      $value = strtolower(trim($level));
      return array_key_exists($value, self::severityMap()) ? $value : self::INFO;
   }
}

