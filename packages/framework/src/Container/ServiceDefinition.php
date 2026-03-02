<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

use Closure;

/**
 * Implement service definition behavior for the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ServiceDefinition
{
   /** @var Closure(ContainerInterface): mixed */
   private Closure $factory;
   /** @var array<int, string> */
   private array $dependencies;

   /**
    * @param callable(ContainerInterface): mixed $factory
    * @param array<int, string> $dependencies
    */
   public function __construct(
      private string $id,
      private ServiceLifetime $lifetime,
      callable $factory,
      array $dependencies = [],
   ) {
      $this->factory = $factory instanceof Closure ? $factory : Closure::fromCallable($factory);
      $this->dependencies = array_values(array_unique(array_map(
         static fn (mixed $dependency): string => (string) $dependency,
         $dependencies
      )));
   }

   /**
    * Get the id.
    *
    * @return string
    */
   public function getId(): string
   {
      return $this->id;
   }

   /**
    * Get the lifetime.
    *
    * @return ServiceLifetime
    */
   public function getLifetime(): ServiceLifetime
   {
      return $this->lifetime;
   }

   /**
    * @return array<int, string>
    */
   public function getDependencies(): array
   {
      return $this->dependencies;
   }

   /**
    * Handle create.
    *
    * @param ContainerInterface $container
    * @return mixed
    */
   public function create(ContainerInterface $container): mixed
   {
      return ($this->factory)($container);
   }
}




