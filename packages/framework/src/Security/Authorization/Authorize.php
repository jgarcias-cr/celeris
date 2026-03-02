<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Authorization;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
/**
 * Implement authorize behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Authorize
{
   /** @var array<int, string> */
   public array $roles;
   /** @var array<int, string> */
   public array $permissions;
   /** @var array<int, string> */
   public array $strategies;

   /**
    * @param array<int, string> $roles
    * @param array<int, string> $permissions
    * @param array<int, string> $strategies
    */
   public function __construct(
      array $roles = [],
      array $permissions = [],
      bool $authenticated = true,
      array $strategies = [],
   ) {
      $this->roles = self::normalize($roles);
      $this->permissions = self::normalize($permissions);
      $this->authenticated = $authenticated;
      $this->strategies = self::normalize($strategies);
   }

   public bool $authenticated;

   /**
    * @param array<int, string> $items
    * @return array<int, string>
    */
   private static function normalize(array $items): array
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


