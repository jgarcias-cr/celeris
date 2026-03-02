<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

/**
 * Purpose: define the contract for logger behavior in the Logging subsystem.
 * How: exposes level-specific helpers and a generic log method.
 * Used in framework: injected into application services and controllers via dependency injection.
 */
interface LoggerInterface
{
   /**
    * @param array<string, mixed> $context
    */
   public function emergency(string $message, array $context = []): void;

   /**
    * @param array<string, mixed> $context
    */
   public function alert(string $message, array $context = []): void;

   /**
    * @param array<string, mixed> $context
    */
   public function critical(string $message, array $context = []): void;

   /**
    * @param array<string, mixed> $context
    */
   public function error(string $message, array $context = []): void;

   /**
    * @param array<string, mixed> $context
    */
   public function warning(string $message, array $context = []): void;

   /**
    * @param array<string, mixed> $context
    */
   public function notice(string $message, array $context = []): void;

   /**
    * @param array<string, mixed> $context
    */
   public function info(string $message, array $context = []): void;

   /**
    * @param array<string, mixed> $context
    */
   public function debug(string $message, array $context = []): void;

   /**
    * @param array<string, mixed> $context
    */
   public function log(string $level, string $message, array $context = []): void;
}

