<?php

declare(strict_types=1);

namespace Celeris\Framework\Config;

/**
 * Purpose: implement config snapshot behavior for the Config subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by config components when config snapshot functionality is required.
 */
final class ConfigSnapshot
{
   /**
    * @param array<string, mixed> $items
    * @param array<string, string> $environment
    * @param array<string, string> $secrets
    */
   public function __construct(
      private array $items,
      private string $fingerprint,
      private array $environment = [],
      private array $secrets = [],
      private float $loadedAt = 0.0,
   ) {}

   public static function empty(): self
   {
      return new self([], 'empty', [], [], microtime(true));
   }

   /**
    * @return array<string, mixed>
    */
   public function getItems(): array
   {
      return $this->items;
   }

   /**
    * Get the fingerprint.
    *
    * @return string
    */
   public function getFingerprint(): string
   {
      return $this->fingerprint;
   }

   /**
    * @return array<string, string>
    */
   public function getEnvironment(): array
   {
      return $this->environment;
   }

   /**
    * @return array<string, string>
    */
   public function getSecrets(): array
   {
      return $this->secrets;
   }

   /**
    * Get the loaded at.
    *
    * @return float
    */
   public function getLoadedAt(): float
   {
      return $this->loadedAt;
   }

   /**
    * @return array<string, string>
    */
   public function getMaskedSecrets(): array
   {
      $masked = [];
      foreach ($this->secrets as $key => $value) {
         $masked[$key] = $this->mask($value);
      }

      return $masked;
   }

   /**
    * Handle mask.
    *
    * @param string $value
    * @return string
    */
   private function mask(string $value): string
   {
      $length = strlen($value);
      if ($length <= 4) {
         return str_repeat('*', max($length, 4));
      }

      return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
   }
}



