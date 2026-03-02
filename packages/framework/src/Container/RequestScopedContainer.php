<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

use Celeris\Framework\Http\RequestContext;

/**
 * Implement request scoped container behavior for the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RequestScopedContainer implements ContainerInterface
{
   /** @var array<string, mixed> */
   private array $instances = [];

   /**
    * Create a new instance.
    *
    * @param Container $root
    * @param RequestContext $requestContext
    * @return mixed
    */
   public function __construct(
      private Container $root,
      private RequestContext $requestContext,
   ) {}

   /**
    * Determine whether has.
    *
    * @param string $id
    * @return bool
    */
   public function has(string $id): bool
   {
      return $this->root->has($id);
   }

   /**
    * Get the value.
    *
    * @param string $id
    * @return mixed
    */
   public function get(string $id): mixed
   {
      return $this->root->resolve($id, $this, new ResolutionState());
   }

   /**
    * Get the request context.
    *
    * @return RequestContext
    */
   public function getRequestContext(): RequestContext
   {
      return $this->requestContext;
   }

   /**
    * Handle clear.
    *
    * @return void
    */
   public function clear(): void
   {
      $this->instances = [];
   }

   /**
    * Determine whether has local instance.
    *
    * @param string $id
    * @return bool
    */
   public function hasLocalInstance(string $id): bool
   {
      return array_key_exists($id, $this->instances);
   }

   /**
    * Get the local instance.
    *
    * @param string $id
    * @return mixed
    */
   public function getLocalInstance(string $id): mixed
   {
      return $this->instances[$id];
   }

   /**
    * Handle store local instance.
    *
    * @param string $id
    * @param mixed $service
    * @return void
    */
   public function storeLocalInstance(string $id, mixed $service): void
   {
      $this->instances[$id] = $service;
   }
}




