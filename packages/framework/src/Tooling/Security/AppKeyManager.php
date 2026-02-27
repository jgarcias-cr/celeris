<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Security;

use Celeris\Framework\Tooling\ToolingException;
use Throwable;

/**
 * Generates and persists APP_KEY values in project environment files.
 */
final class AppKeyManager
{
   public function generate(int $bytes = 32): string
   {
      if ($bytes < 16) {
         throw new ToolingException('APP_KEY byte length must be at least 16.');
      }

      try {
         $raw = random_bytes($bytes);
      } catch (Throwable $exception) {
         throw new ToolingException('Unable to generate APP_KEY: ' . $exception->getMessage(), 0, $exception);
      }

      $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
      return 'base64:' . $encoded;
   }

   /**
    * @return array{env_file:string, created_env:bool, updated:bool, existing_key:bool}
    */
   public function write(
      string $projectRoot,
      string $key,
      string $envFile = '.env',
      bool $force = false,
   ): array {
      $root = rtrim($projectRoot, '/\\');
      if (str_contains($envFile, '..')) {
         throw new ToolingException(sprintf('Unsafe env file path "%s".', $envFile));
      }
      $targetPath = $this->resolvePath($root, $envFile);
      if (!str_starts_with($targetPath, $root . DIRECTORY_SEPARATOR)) {
         throw new ToolingException(sprintf('Refusing to write APP_KEY outside project root: "%s".', $envFile));
      }

      $createdEnv = false;
      $contents = null;
      if (is_file($targetPath)) {
         $read = @file_get_contents($targetPath);
         if (!is_string($read)) {
            throw new ToolingException(sprintf('Unable to read env file "%s".', $targetPath));
         }
         $contents = $read;
      } else {
         $examplePath = $targetPath . '.example';
         if (is_file($examplePath)) {
            $read = @file_get_contents($examplePath);
            if (!is_string($read)) {
               throw new ToolingException(sprintf('Unable to read env template "%s".', $examplePath));
            }
            $contents = $read;
         } else {
            $contents = '';
         }
         $createdEnv = true;
      }

      $lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
      $existingValue = $this->extractAppKey($contents);
      $existingKey = $existingValue !== null && trim($existingValue) !== '';

      if ($existingKey && !$force) {
         if ($createdEnv) {
            $this->persist($targetPath, $contents);
         }

         return [
            'env_file' => $targetPath,
            'created_env' => $createdEnv,
            'updated' => false,
            'existing_key' => true,
         ];
      }

      $replacement = 'APP_KEY=' . $key;
      $updated = false;
      if (preg_match('/^APP_KEY[ \t]*=.*$/m', $contents) === 1) {
         $next = preg_replace('/^APP_KEY[ \t]*=.*$/m', $replacement, $contents, 1);
         if (!is_string($next)) {
            throw new ToolingException('Unable to update APP_KEY in env file.');
         }
         $updated = $next !== $contents;
         $contents = $next;
      } else {
         $trimmed = rtrim($contents, "\r\n");
         $contents = $trimmed === ''
            ? $replacement . $lineEnding
            : $trimmed . $lineEnding . $replacement . $lineEnding;
         $updated = true;
      }

      $this->persist($targetPath, $contents);

      return [
         'env_file' => $targetPath,
         'created_env' => $createdEnv,
         'updated' => $updated,
         'existing_key' => $existingKey,
      ];
   }

   private function resolvePath(string $root, string $envFile): string
   {
      $clean = trim($envFile);
      if ($clean === '') {
         $clean = '.env';
      }

      if (str_starts_with($clean, '/')
         || preg_match('/^[A-Za-z]:[\/\\\\]/', $clean) === 1) {
         return $clean;
      }

      return $root . '/' . ltrim($clean, '/\\');
   }

   private function persist(string $targetPath, string $contents): void
   {
      $dir = dirname($targetPath);
      if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
         throw new ToolingException(sprintf('Unable to create directory "%s".', $dir));
      }

      if (@file_put_contents($targetPath, $contents) === false) {
         throw new ToolingException(sprintf('Unable to write env file "%s".', $targetPath));
      }
   }

   private function extractAppKey(string $contents): ?string
   {
      if (!preg_match('/^APP_KEY[ \t]*=(.*)$/m', $contents, $matches)) {
         return null;
      }

      return trim((string) ($matches[1] ?? ''));
   }
}
