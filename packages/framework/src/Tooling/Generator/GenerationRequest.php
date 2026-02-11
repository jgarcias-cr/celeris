<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Purpose: implement generation request behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when generation request functionality is required.
 */
final class GenerationRequest
{
   /** @var array<string, mixed> */
   private array $options;

   /**
    * @param array<string, mixed> $options
    */
   public function __construct(
      private string $basePath,
      private string $name,
      private string $module = 'App',
      private string $namespaceRoot = 'App',
      array $options = [],
      private bool $overwrite = false,
   ) {
      $this->options = $options;
   }

   /**
    * Handle base path.
    *
    * @return string
    */
   public function basePath(): string
   {
      return rtrim($this->basePath, '/');
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return $this->name;
   }

   /**
    * Handle module.
    *
    * @return string
    */
   public function module(): string
   {
      return $this->module;
   }

   /**
    * Handle namespace root.
    *
    * @return string
    */
   public function namespaceRoot(): string
   {
      return $this->namespaceRoot;
   }

   /**
    * @return array<string, mixed>
    */
   public function options(): array
   {
      return $this->options;
   }

   /**
    * Handle option.
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function option(string $key, mixed $default = null): mixed
   {
      return $this->options[$key] ?? $default;
   }

   /**
    * Handle overwrite.
    *
    * @return bool
    */
   public function overwrite(): bool
   {
      return $this->overwrite;
   }
}



