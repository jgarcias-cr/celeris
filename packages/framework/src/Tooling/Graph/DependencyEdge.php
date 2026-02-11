<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Graph;

/**
 * Purpose: implement dependency edge behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when dependency edge functionality is required.
 */
final class DependencyEdge
{
   /**
    * Create a new instance.
    *
    * @param string $from
    * @param string $to
    * @param string $type
    * @return mixed
    */
   public function __construct(
      private string $from,
      private string $to,
      private string $type = 'depends_on',
   ) {
   }

   /**
    * Create an instance from value.
    *
    * @return string
    */
   public function from(): string
   {
      return $this->from;
   }

   /**
    * Convert to value.
    *
    * @return string
    */
   public function to(): string
   {
      return $this->to;
   }

   /**
    * Handle type.
    *
    * @return string
    */
   public function type(): string
   {
      return $this->type;
   }

   /**
    * @return array{from:string,to:string,type:string}
    */
   public function toArray(): array
   {
      return [
         'from' => $this->from,
         'to' => $this->to,
         'type' => $this->type,
      ];
   }
}



