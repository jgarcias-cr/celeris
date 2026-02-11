<?php

declare(strict_types=1);

namespace Celeris\Framework\Config;

use Closure;

/**
 * Purpose: implement config validator behavior for the Config subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by config components when config validator functionality is required.
 */
final class ConfigValidator
{
   /** @var array<int, string> */
   private array $requiredConfigKeys = [];
   /** @var array<int, string> */
   private array $requiredEnvKeys = [];
   /** @var array<int, string> */
   private array $requiredSecretKeys = [];
   /** @var array<int, Closure(ConfigSnapshot): (string|array<int, string>|null)> */
   private array $rules = [];

   /**
    * Handle require config.
    *
    * @param string $key
    * @return self
    */
   public function requireConfig(string $key): self
   {
      $clean = trim($key);
      if ($clean !== '') {
         $this->requiredConfigKeys[] = $clean;
      }

      return $this;
   }

   /**
    * Handle require env.
    *
    * @param string $key
    * @return self
    */
   public function requireEnv(string $key): self
   {
      $clean = trim($key);
      if ($clean !== '') {
         $this->requiredEnvKeys[] = $clean;
      }

      return $this;
   }

   /**
    * Handle require secret.
    *
    * @param string $key
    * @return self
    */
   public function requireSecret(string $key): self
   {
      $clean = trim($key);
      if ($clean !== '') {
         $this->requiredSecretKeys[] = $clean;
      }

      return $this;
   }

   /**
    * @param callable(ConfigSnapshot): (string|array<int, string>|null) $rule
    */
   public function addRule(callable $rule): self
   {
      $this->rules[] = $rule instanceof Closure ? $rule : Closure::fromCallable($rule);
      return $this;
   }

   /**
    * Handle validate.
    *
    * @param ConfigSnapshot $snapshot
    * @return void
    */
   public function validate(ConfigSnapshot $snapshot): void
   {
      $errors = [];

      foreach ($this->requiredConfigKeys as $key) {
         if (!$this->hasPath($snapshot->getItems(), $key)) {
            $errors[] = sprintf('Missing required config key "%s".', $key);
         }
      }

      foreach ($this->requiredEnvKeys as $key) {
         if (!array_key_exists($key, $snapshot->getEnvironment())) {
            $errors[] = sprintf('Missing required environment key "%s".', $key);
         }
      }
      foreach ($this->requiredSecretKeys as $key) {
         if (!array_key_exists($key, $snapshot->getSecrets())) {
            $errors[] = sprintf('Missing required secret key "%s".', $key);
         }
      }

      foreach ($this->rules as $rule) {
         $result = $rule($snapshot);
         if ($result === null) {
            continue;
         }
         if (is_string($result)) {
            $errors[] = $result;
            continue;
         }
         if (is_array($result)) {
            foreach ($result as $error) {
               $errors[] = (string) $error;
            }
         }
      }

      if ($errors !== []) {
         throw new ConfigValidationException($errors);
      }
   }

   /**
    * @param array<string, mixed> $items
    */
   private function hasPath(array $items, string $key): bool
   {
      $current = $items;
      foreach (explode('.', $key) as $segment) {
         if (!is_array($current) || !array_key_exists($segment, $current)) {
            return false;
         }
         $current = $current[$segment];
      }

      return true;
   }
}



