<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

use Celeris\Framework\Http\RequestContext;

/**
 * Implement container behavior for the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Container implements ContainerInterface
{
   /** @var array<string, ServiceDefinition> */
   private array $definitions;
   /** @var array<string, mixed> */
   private array $singletons = [];

   /**
    * @param array<string, ServiceDefinition> $definitions
    */
   public function __construct(array $definitions = [])
   {
      $this->definitions = $definitions;
   }

   /**
    * Determine whether has.
    *
    * @param string $id
    * @return bool
    */
   public function has(string $id): bool
   {
      return isset($this->definitions[$id]);
   }

   /**
    * Get the value.
    *
    * @param string $id
    * @return mixed
    */
   public function get(string $id): mixed
   {
      return $this->resolve($id, null, new ResolutionState());
   }

   /**
    * Handle for request.
    *
    * @param RequestContext $requestContext
    * @return RequestScopedContainer
    */
   public function forRequest(RequestContext $requestContext): RequestScopedContainer
   {
      return new RequestScopedContainer($this, $requestContext);
   }

   /**
    * Handle validate circular dependencies.
    *
    * @return void
    */
   public function validateCircularDependencies(): void
   {
      /** @var array<string, true> $visiting */
      $visiting = [];
      /** @var array<string, true> $visited */
      $visited = [];
      /** @var array<int, string> $path */
      $path = [];

      $visit = function (string $id) use (&$visit, &$visiting, &$visited, &$path): void {
         if (isset($visiting[$id])) {
            $cycleStart = array_search($id, $path, true);
            $cycle = $cycleStart === false ? [$id, $id] : [...array_slice($path, (int) $cycleStart), $id];
            throw CircularDependencyException::forPath($cycle);
         }

         if (isset($visited[$id])) {
            return;
         }

         $definition = $this->definitions[$id] ?? null;
         if ($definition === null) {
            throw new NotFoundException($id);
         }

         $visiting[$id] = true;
         $path[] = $id;

         foreach ($definition->getDependencies() as $dependencyId) {
            if (!isset($this->definitions[$dependencyId])) {
               throw new NotFoundException($dependencyId);
            }
            $visit($dependencyId);
         }

         array_pop($path);
         unset($visiting[$id]);
         $visited[$id] = true;
      };

      foreach (array_keys($this->definitions) as $serviceId) {
         $visit($serviceId);
      }
   }

   /**
    * Handle clear singletons.
    *
    * @return void
    */
   public function clearSingletons(): void
   {
      $this->singletons = [];
   }

   /**
    * Handle resolve.
    *
    * @param string $id
    * @param ?RequestScopedContainer $requestScope
    * @param ResolutionState $state
    * @return mixed
    */
   public function resolve(string $id, ?RequestScopedContainer $requestScope, ResolutionState $state): mixed
   {
      $definition = $this->definitions[$id] ?? null;
      if ($definition === null) {
         throw new NotFoundException($id);
      }

      $lifetime = $definition->getLifetime();

      if ($lifetime === ServiceLifetime::Singleton && array_key_exists($id, $this->singletons)) {
         return $this->singletons[$id];
      }

      if ($lifetime === ServiceLifetime::Request) {
         if ($requestScope === null) {
            throw new RequestScopeRequiredException($id);
         }

         if ($requestScope->hasLocalInstance($id)) {
            return $requestScope->getLocalInstance($id);
         }
      }

      if ($state->contains($id)) {
         throw CircularDependencyException::forPath($state->cyclePath($id));
      }

      if ($lifetime === ServiceLifetime::Request && $state->hasLifetime(ServiceLifetime::Singleton)) {
         throw new ContainerException(sprintf(
            'Cannot resolve request-scoped service "%s" while building a singleton.',
            $id
         ));
      }

      $state->enter($id, $lifetime);
      try {
         $resolver = new ResolvingContainer($this, $requestScope, $state);
         $service = $definition->create($resolver);
      } finally {
         $state->leave();
      }

      if ($lifetime === ServiceLifetime::Singleton) {
         $this->singletons[$id] = $service;
      }
      if ($lifetime === ServiceLifetime::Request && $requestScope !== null) {
         $requestScope->storeLocalInstance($id, $service);
      }

      return $service;
   }
}




