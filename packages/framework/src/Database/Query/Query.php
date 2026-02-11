<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Query;

/**
 * Purpose: implement query behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when query functionality is required.
 */
final class Query
{
   /** @var array<string, mixed> */
   private array $params;

   /**
    * @param array<string, mixed> $params
    */
   public function __construct(private string $sql, array $params = [])
   {
      $this->params = $params;
   }

   /**
    * Handle sql.
    *
    * @return string
    */
   public function sql(): string
   {
      return $this->sql;
   }

   /**
    * @return array<string, mixed>
    */
   public function params(): array
   {
      return $this->params;
   }
}



