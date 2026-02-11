<?php

declare(strict_types=1);

namespace Celeris\Framework\Config;

use UnexpectedValueException;

/**
 * Purpose: load and normalize config loader data from configured sources.
 * How: reads source inputs, validates shape, and returns normalized runtime objects.
 * Used in framework: invoked by config components when config loader functionality is required.
 */
final class ConfigLoader
{
   /**
    * Create a new instance.
    *
    * @param string $configDirectory
    * @param ?EnvironmentLoader $environmentLoader
    * @param ?ConfigValidator $validator
    * @param bool $injectEnvironmentIntoConfig
    * @return mixed
    */
   public function __construct(
      private string $configDirectory,
      private ?EnvironmentLoader $environmentLoader = null,
      private ?ConfigValidator $validator = null,
      private bool $injectEnvironmentIntoConfig = true,
   ) {}

   /**
    * Handle snapshot.
    *
    * @return ConfigSnapshot
    */
   public function snapshot(): ConfigSnapshot
   {
      $environment = $this->environmentLoader?->load() ?? EnvironmentSnapshot::empty();
      $items = [];
      $fingerprintParts = ['config-dir:' . $this->configDirectory, 'env:' . $environment->getFingerprint()];

      if (is_dir($this->configDirectory)) {
         $files = glob($this->configDirectory . '/*.php');
         if ($files !== false && $files !== []) {
            sort($files);

            foreach ($files as $file) {
               $loaded = require $file;
               if (!is_array($loaded)) {
                  throw new UnexpectedValueException(sprintf('Config file "%s" must return an array.', $file));
               }

               $name = pathinfo($file, PATHINFO_FILENAME);
               $items[$name] = $loaded;

               $stat = @stat($file);
               $fingerprintParts[] = sprintf(
                  '%s:%d:%d',
                  $file,
                  (int) ($stat['mtime'] ?? 0),
                  (int) ($stat['size'] ?? 0),
               );
            }
         }
      }

      if ($this->injectEnvironmentIntoConfig) {
         if (!isset($items['env']) || !is_array($items['env'])) {
            $items['env'] = [];
         }
         $items['env'] = [...$items['env'], ...$environment->publicValues()];
      }

      $snapshot = new ConfigSnapshot(
         $items,
         sha1(implode('|', $fingerprintParts)),
         $environment->publicValues(),
         $environment->secrets(),
         microtime(true),
      );
      if ($this->validator !== null) {
         $this->validator->validate($snapshot);
      }

      return $snapshot;
   }

   /**
    * Handle snapshot if changed.
    *
    * @param ?string $currentFingerprint
    * @return ?ConfigSnapshot
    */
   public function snapshotIfChanged(?string $currentFingerprint): ?ConfigSnapshot
   {
      $snapshot = $this->snapshot();
      if ($snapshot->getFingerprint() === ($currentFingerprint ?? '')) {
         return null;
      }

      return $snapshot;
   }

   /**
    * Return a copy with the environment loader.
    *
    * @param ?EnvironmentLoader $environmentLoader
    * @return self
    */
   public function withEnvironmentLoader(?EnvironmentLoader $environmentLoader): self
   {
      $copy = clone $this;
      $copy->environmentLoader = $environmentLoader;
      return $copy;
   }

   /**
    * Return a copy with the validator.
    *
    * @param ?ConfigValidator $validator
    * @return self
    */
   public function withValidator(?ConfigValidator $validator): self
   {
      $copy = clone $this;
      $copy->validator = $validator;
      return $copy;
   }
}



