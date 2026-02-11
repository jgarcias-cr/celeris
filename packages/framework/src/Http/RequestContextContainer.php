<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

use RuntimeException;
use SplObjectStorage;

/**
 * Purpose: implement request context container behavior for the Http subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by http components when request context container functionality is required.
 */
final class RequestContextContainer
{
   /** @var SplObjectStorage<object, array<int, RequestContext>> */
   private SplObjectStorage $fiberStacks;
   /** @var array<int, RequestContext> */
   private array $mainStack = [];

   /**
    * Create a new instance.
    *
    * @return mixed
    */
   public function __construct()
   {
      $this->fiberStacks = new SplObjectStorage();
   }

   /**
    * Handle enter.
    *
    * @param RequestContext $context
    * @return void
    */
   public function enter(RequestContext $context): void
   {
      $fiber = self::currentFiber();
      if ($fiber === null) {
         $this->mainStack[] = $context;
         return;
      }

      if (!isset($this->fiberStacks[$fiber])) {
         $this->fiberStacks[$fiber] = [];
      }

      $stack = $this->fiberStacks[$fiber];
      $stack[] = $context;
      $this->fiberStacks[$fiber] = $stack;
   }

   /**
    * Handle replace.
    *
    * @param RequestContext $context
    * @return void
    */
   public function replace(RequestContext $context): void
   {
      $fiber = self::currentFiber();
      if ($fiber === null) {
         if ($this->mainStack === []) {
            $this->mainStack[] = $context;
            return;
         }
         $this->mainStack[array_key_last($this->mainStack)] = $context;
         return;
      }

      if (!isset($this->fiberStacks[$fiber])) {
         $this->fiberStacks[$fiber] = [$context];
         return;
      }

      $stack = $this->fiberStacks[$fiber];
      if ($stack === []) {
         $this->fiberStacks[$fiber] = [$context];
         return;
      }

      $stack[array_key_last($stack)] = $context;
      $this->fiberStacks[$fiber] = $stack;
   }

   /**
    * Handle current.
    *
    * @return ?RequestContext
    */
   public function current(): ?RequestContext
   {
      $fiber = self::currentFiber();
      if ($fiber === null) {
         if ($this->mainStack === []) {
            return null;
         }
         return $this->mainStack[array_key_last($this->mainStack)];
      }

      if (!isset($this->fiberStacks[$fiber])) {
         return null;
      }

      $stack = $this->fiberStacks[$fiber];
      if ($stack === []) {
         return null;
      }

      return $stack[array_key_last($stack)];
   }

   /**
    * Handle require current.
    *
    * @return RequestContext
    */
   public function requireCurrent(): RequestContext
   {
      $context = $this->current();
      if ($context === null) {
         throw new RuntimeException('No request context is active for the current execution scope.');
      }

      return $context;
   }

   /**
    * Handle leave.
    *
    * @return void
    */
   public function leave(): void
   {
      $fiber = self::currentFiber();
      if ($fiber === null) {
         array_pop($this->mainStack);
         return;
      }

      if (!isset($this->fiberStacks[$fiber])) {
         return;
      }

      $stack = $this->fiberStacks[$fiber];
      array_pop($stack);
      if ($stack === []) {
         unset($this->fiberStacks[$fiber]);
         return;
      }

      $this->fiberStacks[$fiber] = $stack;
   }

   /**
    * Handle clear.
    *
    * @return void
    */
   public function clear(): void
   {
      $this->mainStack = [];
      $this->fiberStacks = new SplObjectStorage();
   }

   /**
    * Handle current fiber.
    *
    * @return ?object
    */
   private static function currentFiber(): ?object
   {
      if (!class_exists('Fiber')) {
         return null;
      }

      /** @phpstan-ignore-next-line */
      return \Fiber::getCurrent();
   }
}




