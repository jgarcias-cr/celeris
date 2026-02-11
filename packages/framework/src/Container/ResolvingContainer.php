<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: implement resolving container behavior for the Container subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by container components when resolving container functionality is required.
 */
final class ResolvingContainer implements ContainerInterface
{
   /**
    * Create a new instance.
    *
    * @param Container $root
    * @param ?RequestScopedContainer $requestScope
    * @param ResolutionState $state
    * @return mixed
    */
   public function __construct(
      private Container $root,
      private ?RequestScopedContainer $requestScope,
      private ResolutionState $state,
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
      return $this->root->resolve($id, $this->requestScope, $this->state);
   }
}




