<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Authorization;

use Celeris\Framework\Http\RequestContext;

abstract class ModelPolicy
{
   protected function allows(RequestContext $ctx, ?object $resource = null, ?string $permission = null): bool
   {
      $auth = $ctx->getAuth();
      if (!is_array($auth)) {
         return true;
      }

      $roles = $this->normalizeStringList($auth['roles'] ?? []);
      if (in_array('admin', $roles, true)) {
         return true;
      }

      $permissions = $this->normalizeStringList($auth['permissions'] ?? []);
      $ownedResourceIds = $this->ownedResourceIds($auth, $resource);
      if ($roles === [] && $permissions === [] && $ownedResourceIds === []) {
         return true;
      }

      if ($permission !== null && in_array($permission, $permissions, true)) {
         return true;
      }

      if ($permission !== null && $this->impliesPermission($permissions, $permission)) {
         return true;
      }

      $resourceId = $this->resourceId($resource);
      if ($resourceId !== null && in_array($resourceId, $ownedResourceIds, true)) {
         return true;
      }

      return false;
   }

   /**
    * @param array<string, mixed> $auth
    * @return array<int, int|string>
    */
   protected function ownedResourceIds(array $auth, ?object $resource = null): array
   {
      $claimKey = $this->ownershipClaimKey($resource);
      if ($claimKey === null) {
         return [];
      }

      return $this->normalizeIdentifierList($auth[$claimKey] ?? []);
   }

   protected function ownershipClaimKey(?object $resource = null): ?string
   {
      $baseName = $this->resourceBaseName($resource);
      if ($baseName === null || $baseName === '') {
         return null;
      }

      return $this->snakeCase($baseName) . '_ids';
   }

   /**
    * @param array<int, string> $permissions
    */
   protected function impliesPermission(array $permissions, string $requiredPermission): bool
   {
      if (str_ends_with($requiredPermission, ':read')) {
         $writePermission = substr($requiredPermission, 0, -4) . ':write';
         return in_array($writePermission, $permissions, true);
      }

      return false;
   }

   protected function resourceId(?object $resource): int|string|null
   {
      if (!is_object($resource) || !property_exists($resource, 'id')) {
         return null;
      }

      $id = $resource->id;
      if (is_int($id) || is_string($id)) {
         return $id;
      }

      return null;
   }

   protected function resourceBaseName(?object $resource): ?string
   {
      if (!is_object($resource)) {
         return null;
      }

      $className = $resource::class;
      $position = strrpos($className, '\\');

      return $position === false ? $className : substr($className, $position + 1);
   }

   protected function snakeCase(string $value): string
   {
      $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

      return strtolower((string) $normalized);
   }

   /**
    * @return array<int, string>
    */
   protected function normalizeStringList(mixed $value): array
   {
      if (is_string($value)) {
         $value = [$value];
      }
      if (!is_array($value)) {
         return [];
      }

      $normalized = [];
      foreach ($value as $item) {
         $clean = trim((string) $item);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      return array_values(array_unique($normalized));
   }

   /**
    * @return array<int, int|string>
    */
   protected function normalizeIdentifierList(mixed $value): array
   {
      if (!is_array($value)) {
         return [];
      }

      $normalized = [];
      foreach ($value as $item) {
         if (is_int($item)) {
            $normalized[] = $item;
            continue;
         }

         if (!is_string($item)) {
            continue;
         }

         $clean = trim($item);
         if ($clean === '') {
            continue;
         }

         if (preg_match('/^-?\d+$/', $clean) === 1) {
            $normalized[] = (int) $clean;
            continue;
         }

         $normalized[] = $clean;
      }

      return array_values(array_unique($normalized, SORT_REGULAR));
   }
}
