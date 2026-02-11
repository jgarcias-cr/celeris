<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Auth;

/**
 * Purpose: implement service auth result behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when service auth result functionality is required.
 */
final class ServiceAuthResult
{
   /**
    * Create a new instance.
    *
    * @param bool $authenticated
    * @param ?ServicePrincipal $principal
    * @param ?string $error
    * @return mixed
    */
   private function __construct(
      private bool $authenticated,
      private ?ServicePrincipal $principal,
      private ?string $error,
   ) {
   }

   /**
    * Handle accepted.
    *
    * @param ServicePrincipal $principal
    * @return self
    */
   public static function accepted(ServicePrincipal $principal): self
   {
      return new self(true, $principal, null);
   }

   /**
    * Handle rejected.
    *
    * @param string $error
    * @return self
    */
   public static function rejected(string $error): self
   {
      return new self(false, null, $error);
   }

   /**
    * Handle authenticated.
    *
    * @return bool
    */
   public function authenticated(): bool
   {
      return $this->authenticated;
   }

   /**
    * Handle principal.
    *
    * @return ?ServicePrincipal
    */
   public function principal(): ?ServicePrincipal
   {
      return $this->principal;
   }

   /**
    * Handle error.
    *
    * @return ?string
    */
   public function error(): ?string
   {
      return $this->error;
   }
}



