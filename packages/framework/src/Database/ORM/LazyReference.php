<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

/**
 * Implement lazy reference behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class LazyReference
{
   private bool $loaded = false;
   private mixed $value = null;

   /** @param callable(): mixed $loader */
   public function __construct(private $loader)
   {
   }

   /**
    * Determine whether is loaded.
    *
    * @return bool
    */
   public function isLoaded(): bool
   {
      return $this->loaded;
   }

   /**
    * Handle load.
    *
    * @return mixed
    */
   public function load(): mixed
   {
      if ($this->loaded) {
         return $this->value;
      }

      $loader = $this->loader;
      $this->value = $loader();
      $this->loaded = true;
      return $this->value;
   }

   /**
    * Get the if loaded.
    *
    * @return mixed
    */
   public function getIfLoaded(): mixed
   {
      return $this->loaded ? $this->value : null;
   }
}



