<?php

declare(strict_types=1);

namespace Celeris\Framework\Config;

use RuntimeException;

/**
 * Purpose: load and normalize environment loader data from configured sources.
 * How: reads source inputs, validates shape, and returns normalized runtime objects.
 * Used in framework: invoked by config components when environment loader functionality is required.
 */
final class EnvironmentLoader
{
   /**
    * Create a new instance.
    *
    * @param ?string $envFilePath
    * @param ?string $secretsDirectory
    * @param bool $overrideExisting
    * @param bool $applyToSuperglobals
    * @return mixed
    */
   public function __construct(
      private ?string $envFilePath = null,
      private ?string $secretsDirectory = null,
      private bool $overrideExisting = false,
      private bool $applyToSuperglobals = false,
   ) {}

   /**
    * Handle load.
    *
    * @return EnvironmentSnapshot
    */
   public function load(): EnvironmentSnapshot
   {
      $values = $this->readProcessEnvironment();
      $fingerprintParts = [];

      $envValues = $this->loadDotEnvFile($fingerprintParts, $values);
      foreach ($envValues as $key => $value) {
         if (!$this->overrideExisting && array_key_exists($key, $values)) {
            continue;
         }
         $values[$key] = $value;
      }

      $secrets = $this->loadSecrets($fingerprintParts);
      foreach ($secrets as $key => $value) {
         if (!$this->overrideExisting && array_key_exists($key, $values)) {
            continue;
         }
         $values[$key] = $value;
      }

      if ($this->applyToSuperglobals) {
         foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
         }
      }

      ksort($values);
      ksort($secrets);
      $fingerprintParts[] = 'values:' . sha1((string) json_encode($values, JSON_UNESCAPED_UNICODE));
      $fingerprintParts[] = 'secrets:' . sha1((string) json_encode($secrets, JSON_UNESCAPED_UNICODE));
      $fingerprint = sha1(implode('|', $fingerprintParts));
      return new EnvironmentSnapshot($values, $secrets, $fingerprint);
   }

   /**
    * @return array<string, string>
    */
   private function readProcessEnvironment(): array
   {
      $values = [];
      foreach ($_ENV as $key => $value) {
         if (is_string($key) && (is_string($value) || is_numeric($value))) {
            $values[$key] = (string) $value;
         }
      }

      foreach ($_SERVER as $key => $value) {
         if (!is_string($key) || !(is_string($value) || is_numeric($value))) {
            continue;
         }

         if (!array_key_exists($key, $values)) {
            $values[$key] = (string) $value;
         }
      }

      return $values;
   }

   /**
    * @param array<int, string> $fingerprintParts
    * @param array<string, string> $currentValues
    * @return array<string, string>
    */
   private function loadDotEnvFile(array &$fingerprintParts, array $currentValues): array
   {
      if ($this->envFilePath === null || $this->envFilePath === '' || !is_file($this->envFilePath)) {
         return [];
      }

      $stat = @stat($this->envFilePath);
      $fingerprintParts[] = sprintf(
         'env:%s:%d:%d',
         $this->envFilePath,
         (int) ($stat['mtime'] ?? 0),
         (int) ($stat['size'] ?? 0)
      );

      $content = @file_get_contents($this->envFilePath);
      if ($content === false) {
         throw new RuntimeException(sprintf('Unable to read environment file "%s".', $this->envFilePath));
      }

      $result = [];
      $lines = preg_split('/\R/', $content) ?: [];
      foreach ($lines as $line) {
         $trimmed = trim($line);
         if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
         }

         if (str_starts_with($trimmed, 'export ')) {
            $trimmed = trim(substr($trimmed, 7));
         }

         $parts = explode('=', $trimmed, 2);
         if (count($parts) !== 2) {
            continue;
         }

         $key = trim($parts[0]);
         $rawValue = trim($parts[1]);
         if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
         }

         $value = $this->parseValue($rawValue);
         $resolved = $this->resolveReferences($value, $result + $currentValues);
         $result[$key] = $resolved;
      }

      return $result;
   }

   /**
    * @param array<int, string> $fingerprintParts
    * @return array<string, string>
    */
   private function loadSecrets(array &$fingerprintParts): array
   {
      if ($this->secretsDirectory === null || $this->secretsDirectory === '' || !is_dir($this->secretsDirectory)) {
         return [];
      }

      $files = glob(rtrim($this->secretsDirectory, '/') . '/*');
      if ($files === false || $files === []) {
         return [];
      }

      sort($files);
      $secrets = [];
      foreach ($files as $file) {
         if (!is_file($file)) {
            continue;
         }

         $key = basename($file);
         if ($key === '' || str_starts_with($key, '.')) {
            continue;
         }
         if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
         }

         $value = @file_get_contents($file);
         if ($value === false) {
            throw new RuntimeException(sprintf('Unable to read secret file "%s".', $file));
         }

         $stat = @stat($file);
         $fingerprintParts[] = sprintf(
            'secret:%s:%d:%d',
            $file,
            (int) ($stat['mtime'] ?? 0),
            (int) ($stat['size'] ?? 0),
         );
         $secrets[$key] = rtrim($value, "\r\n");
      }

      return $secrets;
   }

   /**
    * Handle parse value.
    *
    * @param string $rawValue
    * @return string
    */
   private function parseValue(string $rawValue): string
   {
      if ($rawValue === '') {
         return '';
      }

      if (str_starts_with($rawValue, "'") && str_ends_with($rawValue, "'")) {
         return substr($rawValue, 1, -1);
      }

      if (str_starts_with($rawValue, '"') && str_ends_with($rawValue, '"')) {
         $inner = substr($rawValue, 1, -1);
         return stripcslashes($inner);
      }

      $hashPos = strpos($rawValue, ' #');
      if ($hashPos !== false) {
         return trim(substr($rawValue, 0, $hashPos));
      }

      return $rawValue;
   }

   /**
    * @param array<string, string> $resolvedValues
    */
   private function resolveReferences(string $value, array $resolvedValues): string
   {
      return (string) preg_replace_callback(
         '/\$\{([A-Za-z_][A-Za-z0-9_]*)\}|\$([A-Za-z_][A-Za-z0-9_]*)/',
         static function (array $matches) use ($resolvedValues): string {
            $key = $matches[1] !== '' ? $matches[1] : $matches[2];
            return $resolvedValues[$key] ?? '';
         },
         $value
      );
   }
}



