<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

/**
 * Purpose: centralize supported log levels and ordering for filtering.
 * How: defines canonical level names and numeric severity ranking.
 * Used in framework: consumed by logger implementations for threshold checks.
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

   public static function normalize(string $level): string
   {
      $value = strtolower(trim($level));
      return array_key_exists($value, self::severityMap()) ? $value : self::INFO;
   }
}

