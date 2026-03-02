<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Query;

/**
 * Implement query behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



