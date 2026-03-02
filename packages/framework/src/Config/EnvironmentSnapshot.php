<?php

declare(strict_types=1);

namespace Celeris\Framework\Config;

/**
 * Implement environment snapshot behavior for the Config subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class EnvironmentSnapshot
{
   /** @var array<string, string> */
   private array $values;
   /** @var array<string, string> */
   private array $secrets;
   /** @var array<string, bool> */
   private array $secretIndex;

   /**
    * @param array<string, string> $values
    * @param array<string, string> $secrets
    */
   public function __construct(array $values, array $secrets, private string $fingerprint)
   {
      $this->values = $values;
      $this->secrets = $secrets;
      $this->secretIndex = [];
      foreach (array_keys($secrets) as $key) {
         $this->secretIndex[$key] = true;
      }
   }

   public static function empty(): self
   {
      return new self([], [], 'env-empty');
   }

   /**
    * Get the value.
    *
    * @param string $key
    * @param ?string $default
    * @return ?string
    */
   public function get(string $key, ?string $default = null): ?string
   {
      return $this->values[$key] ?? $default;
   }

   /**
    * Determine whether has.
    *
    * @param string $key
    * @return bool
    */
   public function has(string $key): bool
   {
      return array_key_exists($key, $this->values);
   }

   /**
    * Get the secret.
    *
    * @param string $key
    * @param ?string $default
    * @return ?string
    */
   public function getSecret(string $key, ?string $default = null): ?string
   {
      return $this->secrets[$key] ?? $default;
   }

   /**
    * @return array<string, string>
    */
   public function values(): array
   {
      return $this->values;
   }

   /**
    * @return array<string, string>
    */
   public function publicValues(): array
   {
      $values = [];
      foreach ($this->values as $key => $value) {
         if (!isset($this->secretIndex[$key])) {
            $values[$key] = $value;
         }
      }

      return $values;
   }

   /**
    * @return array<string, string>
    */
   public function secrets(): array
   {
      return $this->secrets;
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
    * @return array<string, string>
    */
   public function maskedValues(): array
   {
      $masked = [];
      foreach ($this->values as $key => $value) {
         $masked[$key] = isset($this->secretIndex[$key]) ? self::mask($value) : $value;
      }

      return $masked;
   }

   /**
    * @return array<string, string>
    */
   public function maskedSecrets(): array
   {
      $masked = [];
      foreach ($this->secrets as $key => $value) {
         $masked[$key] = self::mask($value);
      }

      return $masked;
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
    * Handle mask.
    *
    * @param string $value
    * @return string
    */
   private static function mask(string $value): string
   {
      $length = strlen($value);
      if ($length <= 4) {
         return str_repeat('*', max(4, $length));
      }

      return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
   }
}



