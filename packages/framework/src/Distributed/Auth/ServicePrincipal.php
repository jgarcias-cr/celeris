<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Auth;

/**
 * Implement service principal behavior for the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ServicePrincipal
{
   /**
    * Create a new instance.
    *
    * @param string $serviceId
    * @param int $issuedAt
    * @param string $signature
    * @return mixed
    */
   public function __construct(
      private string $serviceId,
      private int $issuedAt,
      private string $signature,
   ) {
   }

   /**
    * Handle service id.
    *
    * @return string
    */
   public function serviceId(): string
   {
      return $this->serviceId;
   }

   /**
    * Determine whether issued at.
    *
    * @return int
    */
   public function issuedAt(): int
   {
      return $this->issuedAt;
   }

   /**
    * Handle signature.
    *
    * @return string
    */
   public function signature(): string
   {
      return $this->signature;
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'service_id' => $this->serviceId,
         'issued_at' => $this->issuedAt,
         'signature' => $this->signature,
      ];
   }
}



