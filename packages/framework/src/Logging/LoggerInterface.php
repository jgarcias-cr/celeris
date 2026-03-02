<?php

declare(strict_types=1);

namespace Celeris\Framework\Logging;

/**
 * Define the contract for logger behavior in the Logging subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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

