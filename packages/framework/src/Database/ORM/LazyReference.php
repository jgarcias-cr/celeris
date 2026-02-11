<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

/**
 * Purpose: implement lazy reference behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when lazy reference functionality is required.
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



