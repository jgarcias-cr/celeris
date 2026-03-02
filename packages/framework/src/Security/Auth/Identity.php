<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Implement identity behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Identity
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
      private float $authenticatedAt = 0.0,
   ) {
      $this->roles = self::normalizeStringList($roles);
      $this->permissions = self::normalizeStringList($permissions);
      $this->attributes = $attributes;
      $this->authenticatedAt = $authenticatedAt > 0.0 ? $authenticatedAt : microtime(true);
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
    * Handle authenticated at.
    *
    * @return float
    */
   public function authenticatedAt(): float
   {
      return $this->authenticatedAt;
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'subject' => $this->subject,
         'roles' => $this->roles,
         'permissions' => $this->permissions,
         'attributes' => $this->attributes,
         'authenticated_at' => $this->authenticatedAt,
      ];
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



