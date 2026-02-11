<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Purpose: implement stored credential behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when stored credential functionality is required.
 */
final class StoredCredential
{
   /** @var array<int, string> */
   private array $roles;
   /** @var array<int, string> */
   private array $permissions;
   /** @var array<string, mixed> */
   private array $attributes;

   /**
    * @param array<int, string> $roles
    * @param array<int, string> $permissions
    * @param array<string, mixed> $attributes
    */
   public function __construct(
      private string $subject,
      array $roles = [],
      array $permissions = [],
      array $attributes = [],
      private ?float $expiresAt = null,
      private ?string $tokenId = null,
   ) {
      $this->roles = self::normalizeStringList($roles);
      $this->permissions = self::normalizeStringList($permissions);
      $this->attributes = $attributes;
   }

   /**
    * Handle subject.
    *
    * @return string
    */
   public function subject(): string
   {
      return $this->subject;
   }

   /**
    * @return array<int, string>
    */
   public function roles(): array
   {
      return $this->roles;
   }

   /**
    * @return array<int, string>
    */
   public function permissions(): array
   {
      return $this->permissions;
   }

   /**
    * @return array<string, mixed>
    */
   public function attributes(): array
   {
      return $this->attributes;
   }

   /**
    * Handle expires at.
    *
    * @return ?float
    */
   public function expiresAt(): ?float
   {
      return $this->expiresAt;
   }

   /**
    * Convert to ken id.
    *
    * @return ?string
    */
   public function tokenId(): ?string
   {
      return $this->tokenId;
   }

   /**
    * Determine whether is expired.
    *
    * @param ?float $now
    * @return bool
    */
   public function isExpired(?float $now = null): bool
   {
      if ($this->expiresAt === null) {
         return false;
      }

      $reference = $now ?? microtime(true);
      return $reference >= $this->expiresAt;
   }

   /**
    * @param array<int, string> $items
    * @return array<int, string>
    */
   private static function normalizeStringList(array $items): array
   {
      $normalized = [];
      foreach ($items as $item) {
         $clean = trim((string) $item);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      return array_values(array_unique($normalized));
   }
}



