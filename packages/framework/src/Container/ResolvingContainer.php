<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Implement resolving container behavior for the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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




