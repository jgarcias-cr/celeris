<?php

declare(strict_types=1);

namespace Celeris\Framework\Config;

/**
 * Purpose: implement config repository behavior for the Config subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by config components when config repository functionality is required.
 */
final class ConfigRepository
{
   /**
    * @param array<string, mixed> $items
    * @param array<string, string> $environment
    * @param array<string, string> $secrets
    */
   public function __construct(
      private array $items = [],
      private array $environment = [],
      private array $secrets = [],
      private string $fingerprint = 'config-empty',
      private float $loadedAt = 0.0
   )
   {
   }

   /**
    * @return array<string, mixed>
    */
   public function all(): array
   {
      return $this->items;
   }

   /**
    * Determine whether has.
    *
    * @param string $key
    * @return bool
    */
   public function has(string $key): bool
   {
      return $this->resolve($key, false)[0];
   }

   /**
    * Get the value.
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function get(string $key, mixed $default = null): mixed
   {
      [$found, $value] = $this->resolve($key, true);
      return $found ? $value : $default;
   }

   /**
    * Handle env.
    *
    * @param string $key
    * @param ?string $default
    * @return ?string
    */
   public function env(string $key, ?string $default = null): ?string
   {
      return $this->environment[$key] ?? $default;
   }

   /**
    * Determine whether has env.
    *
    * @param string $key
    * @return bool
    */
   public function hasEnv(string $key): bool
   {
      return array_key_exists($key, $this->environment);
   }

   /**
    * Handle secret.
    *
    * @param string $key
    * @param ?string $default
    * @return ?string
    */
   public function secret(string $key, ?string $default = null): ?string
   {
      return $this->secrets[$key] ?? $default;
   }

   /**
    * Determine whether has secret.
    *
    * @param string $key
    * @return bool
    */
   public function hasSecret(string $key): bool
   {
      return array_key_exists($key, $this->secrets);
   }

   /**
    * @return array<string, string>
    */
   public function environment(): array
   {
      return $this->environment;
   }

   /**
    * @return array<int, string>
    */
   public function secretKeys(): array
   {
      $keys = array_keys($this->secrets);
      sort($keys);
      return $keys;
   }

   /**
    * Handle fingerprint.
    *
    * @return string
    */
   public function fingerprint(): string
   {
      return $this->fingerprint;
   }

   /**
    * Handle loaded at.
    *
    * @return float
    */
   public function loadedAt(): float
   {
      return $this->loadedAt;
   }

   /**
    * @return array<string, mixed>
    */
   public function debug(): array
   {
      $maskedSecrets = [];
      foreach ($this->secrets as $key => $value) {
         $maskedSecrets[$key] = $this->mask($value);
      }

      return [
         'config' => $this->items,
         'environment' => $this->environment,
         'secrets' => $maskedSecrets,
         'fingerprint' => $this->fingerprint,
         'loaded_at' => $this->loadedAt,
      ];
   }

   /**
    * @return array{0: bool, 1: mixed}
    */
   private function resolve(string $key, bool $includeValue): array
   {
      if ($key === '') {
         return [false, null];
      }

      $current = $this->items;
      foreach (explode('.', $key) as $segment) {
         if (!is_array($current) || !array_key_exists($segment, $current)) {
            return [false, null];
         }
         $current = $current[$segment];
      }

      return $includeValue ? [true, $current] : [true, null];
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



