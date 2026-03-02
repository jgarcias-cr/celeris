<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Implement service registry behavior for the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ServiceRegistry
{
   /** @var array<string, ServiceDefinition> */
   private array $definitions = [];

   /**
    * Handle register.
    *
    * @param ServiceDefinition $definition
    * @param bool $override
    * @return void
    */
   public function register(ServiceDefinition $definition, bool $override = false): void
   {
      $id = $definition->getId();
      if (!$override && isset($this->definitions[$id])) {
         throw new ContainerException(sprintf('Service "%s" is already registered.', $id));
      }

      $this->definitions[$id] = $definition;
   }

   /**
    * @param callable(ContainerInterface): mixed $factory
    * @param array<int, string> $dependencies
    */
   public function singleton(string $id, callable $factory, array $dependencies = [], bool $override = false): void
   {
      $this->register(new ServiceDefinition($id, ServiceLifetime::Singleton, $factory, $dependencies), $override);
   }

   /**
    * @param callable(ContainerInterface): mixed $factory
    * @param array<int, string> $dependencies
    */
   public function request(string $id, callable $factory, array $dependencies = [], bool $override = false): void
   {
      $this->register(new ServiceDefinition($id, ServiceLifetime::Request, $factory, $dependencies), $override);
   }

   /**
    * @param callable(ContainerInterface): mixed $factory
    * @param array<int, string> $dependencies
    */
   public function transient(string $id, callable $factory, array $dependencies = [], bool $override = false): void
   {
      $this->register(new ServiceDefinition($id, ServiceLifetime::Transient, $factory, $dependencies), $override);
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
    * @return ServiceDefinition
    */
   public function get(string $id): ServiceDefinition
   {
      if (!isset($this->definitions[$id])) {
         throw new NotFoundException($id);
      }

      return $this->definitions[$id];
   }

   /**
    * @return array<string, ServiceDefinition>
    */
   public function all(): array
   {
      return $this->definitions;
   }

   /**
    * Handle copy.
    *
    * @return self
    */
   public function copy(): self
   {
      $copy = new self();
      foreach ($this->definitions as $definition) {
         $copy->register($definition, true);
      }

      return $copy;
   }

   /**
    * Handle merge from.
    *
    * @param self $other
    * @param bool $override
    * @return void
    */
   public function mergeFrom(self $other, bool $override = false): void
   {
      foreach ($other->all() as $definition) {
         $this->register($definition, $override);
      }
   }
}




