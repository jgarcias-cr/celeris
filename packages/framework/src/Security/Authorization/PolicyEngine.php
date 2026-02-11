<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Authorization;

use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Security\SecurityException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Reflector;

/**
 * Purpose: orchestrate policy engine workflows within Security.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when policy engine functionality is required.
 */
final class PolicyEngine
{
   /** @var array<string, array<int, Authorize>> */
   private array $attributeCache = [];

   /**
    * Handle authorize.
    *
    * @param RequestContext $ctx
    * @param mixed $handler
    * @return void
    */
   public function authorize(RequestContext $ctx, mixed $handler): void
   {
      $rules = $this->resolveRules($handler);
      if ($rules === []) {
         return;
      }

      $auth = $ctx->getAuth();
      $principalRoles = $this->normalizeList($auth['roles'] ?? []);
      $principalPermissions = $this->normalizeList($auth['permissions'] ?? []);
      $strategy = trim((string) ($auth['strategy'] ?? ''));

      foreach ($rules as $rule) {
         if ($rule->authenticated && !is_array($auth)) {
            throw new SecurityException(
               'Authentication is required for this resource.',
               401,
               ['www-authenticate' => 'Bearer']
            );
         }

         if ($rule->strategies !== [] && !in_array($strategy, $rule->strategies, true)) {
            throw new SecurityException('Access denied for current authentication strategy.', 403);
         }

         if ($rule->roles !== [] && array_intersect($rule->roles, $principalRoles) === []) {
            throw new SecurityException('Missing required role.', 403);
         }

         foreach ($rule->permissions as $permission) {
            if (!in_array($permission, $principalPermissions, true)) {
               throw new SecurityException('Missing required permission.', 403);
            }
         }
      }
   }

   /**
    * @return array<int, Authorize>
    */
   private function resolveRules(mixed $handler): array
   {
      $cacheKey = $this->cacheKey($handler);
      if (isset($this->attributeCache[$cacheKey])) {
         return $this->attributeCache[$cacheKey];
      }

      $rules = [];

      $methodReflector = $this->resolveMethodReflector($handler);
      if ($methodReflector !== null) {
         foreach ($this->extractAuthorizeAttributes($methodReflector) as $attribute) {
            $rules[] = $attribute;
         }
      }

      $classReflector = $this->resolveClassReflector($handler);
      if ($classReflector !== null) {
         foreach ($this->extractAuthorizeAttributes($classReflector) as $attribute) {
            $rules[] = $attribute;
         }
      }

      $this->attributeCache[$cacheKey] = $rules;
      return $rules;
   }

   /**
    * Handle cache key.
    *
    * @param mixed $handler
    * @return string
    */
   private function cacheKey(mixed $handler): string
   {
      if (is_array($handler) && isset($handler[0], $handler[1])) {
         $className = is_object($handler[0]) ? $handler[0]::class : (string) $handler[0];
         return $className . '::' . (string) $handler[1];
      }
      if (is_string($handler)) {
         return $handler;
      }
      if (is_object($handler)) {
         return $handler::class . '#' . spl_object_id($handler);
      }

      return 'callable_' . md5(serialize($handler));
   }

   /**
    * Handle resolve method reflector.
    *
    * @param mixed $handler
    * @return ReflectionMethod|ReflectionFunction|null
    */
   private function resolveMethodReflector(mixed $handler): ReflectionMethod|ReflectionFunction|null
   {
      if (is_array($handler) && isset($handler[0], $handler[1])) {
         $target = is_object($handler[0]) ? $handler[0] : (string) $handler[0];
         return new ReflectionMethod($target, (string) $handler[1]);
      }

      if (is_string($handler) && str_contains($handler, '@')) {
         [$className, $methodName] = explode('@', $handler, 2);
         return new ReflectionMethod($className, $methodName);
      }

      if (is_callable($handler)) {
         return new ReflectionFunction(\Closure::fromCallable($handler));
      }

      return null;
   }

   /**
    * Handle resolve class reflector.
    *
    * @param mixed $handler
    * @return ?ReflectionClass
    */
   private function resolveClassReflector(mixed $handler): ?ReflectionClass
   {
      if (is_array($handler) && isset($handler[0])) {
         $className = is_object($handler[0]) ? $handler[0]::class : (string) $handler[0];
         return class_exists($className) ? new ReflectionClass($className) : null;
      }

      if (is_string($handler) && str_contains($handler, '@')) {
         [$className] = explode('@', $handler, 2);
         return class_exists($className) ? new ReflectionClass($className) : null;
      }

      if (is_object($handler) && !($handler instanceof \Closure)) {
         return new ReflectionClass($handler);
      }

      return null;
   }

   /**
    * @return array<int, Authorize>
    */
   private function extractAuthorizeAttributes(Reflector $reflector): array
   {
      $rules = [];
      foreach ($reflector->getAttributes(Authorize::class) as $attribute) {
         $instance = $attribute->newInstance();
         if ($instance instanceof Authorize) {
            $rules[] = $instance;
         }
      }

      return $rules;
   }

   /**
    * @return array<int, string>
    */
   private function normalizeList(mixed $value): array
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
}



