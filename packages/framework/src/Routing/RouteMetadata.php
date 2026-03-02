<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

/**
 * Implement route metadata behavior for the Routing subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RouteMetadata
{
   /**
    * @param array<int, string> $tags
    */
   public function __construct(
      private ?string $name = null,
      private ?string $summary = null,
      private ?string $description = null,
      private array $tags = [],
      private bool $deprecated = false,
      private ?string $version = null,
      private ?string $operationId = null,
   ) {}

   /**
    * Handle name.
    *
    * @return ?string
    */
   public function name(): ?string
   {
      return $this->name;
   }

   /**
    * Handle summary.
    *
    * @return ?string
    */
   public function summary(): ?string
   {
      return $this->summary;
   }

   /**
    * Handle description.
    *
    * @return ?string
    */
   public function description(): ?string
   {
      return $this->description;
   }

   /**
    * @return array<int, string>
    */
   public function tags(): array
   {
      return $this->tags;
   }

   /**
    * Handle deprecated.
    *
    * @return bool
    */
   public function deprecated(): bool
   {
      return $this->deprecated;
   }

   /**
    * Handle version.
    *
    * @return ?string
    */
   public function version(): ?string
   {
      return $this->version;
   }

   /**
    * Handle operation id.
    *
    * @return ?string
    */
   public function operationId(): ?string
   {
      return $this->operationId;
   }

   /**
    * Return a copy with the name.
    *
    * @param ?string $name
    * @return self
    */
   public function withName(?string $name): self
   {
      $copy = clone $this;
      $copy->name = $name;
      return $copy;
   }

   /**
    * Return a copy with the summary.
    *
    * @param ?string $summary
    * @return self
    */
   public function withSummary(?string $summary): self
   {
      $copy = clone $this;
      $copy->summary = $summary;
      return $copy;
   }

   /**
    * Return a copy with the description.
    *
    * @param ?string $description
    * @return self
    */
   public function withDescription(?string $description): self
   {
      $copy = clone $this;
      $copy->description = $description;
      return $copy;
   }

   /**
    * @param array<int, string> $tags
    */
   public function withTags(array $tags): self
   {
      $copy = clone $this;
      $copy->tags = self::normalizeTags($tags);
      return $copy;
   }

   /**
    * Return a copy with the deprecated.
    *
    * @param bool $deprecated
    * @return self
    */
   public function withDeprecated(bool $deprecated): self
   {
      $copy = clone $this;
      $copy->deprecated = $deprecated;
      return $copy;
   }

   /**
    * Return a copy with the version.
    *
    * @param ?string $version
    * @return self
    */
   public function withVersion(?string $version): self
   {
      $copy = clone $this;
      $copy->version = $version;
      return $copy;
   }

   /**
    * Return a copy with the operation id.
    *
    * @param ?string $operationId
    * @return self
    */
   public function withOperationId(?string $operationId): self
   {
      $copy = clone $this;
      $copy->operationId = $operationId;
      return $copy;
   }

   /**
    * @param array<int, string> $tags
    */
   public function withAddedTags(array $tags): self
   {
      $copy = clone $this;
      $copy->tags = self::normalizeTags([...$this->tags, ...$tags]);
      return $copy;
   }

   /**
    * Return a copy with the name prefix.
    *
    * @param string $prefix
    * @return self
    */
   public function withNamePrefix(string $prefix): self
   {
      $prefix = trim($prefix);
      if ($prefix === '') {
         return $this;
      }

      if ($this->name === null || $this->name === '') {
         return $this->withName($prefix);
      }

      return $this->withName($prefix . '.' . $this->name);
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'name' => $this->name,
         'summary' => $this->summary,
         'description' => $this->description,
         'tags' => $this->tags,
         'deprecated' => $this->deprecated,
         'version' => $this->version,
         'operation_id' => $this->operationId,
      ];
   }

   /**
    * @param array<int, string> $tags
    * @return array<int, string>
    */
   private static function normalizeTags(array $tags): array
   {
      $normalized = [];
      foreach ($tags as $tag) {
         $clean = trim((string) $tag);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      return array_values(array_unique($normalized));
   }
}




