<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord;

/**
 * Purpose: offer an explicit query DSL for Active Record models.
 * How: accumulates deterministic filters/sorting/paging and delegates execution to `ActiveRecordManager`.
 * Used in framework: returned by `ActiveRecordModel::query()` for API-style model retrieval.
 *
 * @template TModel of ActiveRecordModel
 */
final class ActiveRecordQuery
{
   /** @var array<int, array{property: string, operator: string, value: mixed}> */
   private array $conditions = [];

   /** @var array<int, array{property: string, direction: string}> */
   private array $orders = [];

   private ?int $limit = null;
   private ?int $offset = null;

   /**
    * @template T of ActiveRecordModel
    * @param ActiveRecordManager $manager
    * @param class-string<T> $modelClass
    */
   public function __construct(
      private ActiveRecordManager $manager,
      private string $modelClass,
   ) {
   }

   /**
    * Add a condition using `=` operator.
    *
    * @param string $property
    * @param mixed $value
    * @return $this
    */
   public function where(string $property, mixed $value): self
   {
      $this->conditions[] = [
         'property' => trim($property),
         'operator' => '=',
         'value' => $value,
      ];
      return $this;
   }

   /**
    * Add a condition with an explicit operator.
    *
    * @param string $property
    * @param string $operator
    * @param mixed $value
    * @return $this
    */
   public function whereOp(string $property, string $operator, mixed $value): self
   {
      $this->conditions[] = [
         'property' => trim($property),
         'operator' => trim($operator),
         'value' => $value,
      ];
      return $this;
   }

   /**
    * Add an ORDER BY clause.
    *
    * @param string $property
    * @param string $direction
    * @return $this
    */
   public function orderBy(string $property, string $direction = 'ASC'): self
   {
      $this->orders[] = [
         'property' => trim($property),
         'direction' => strtoupper(trim($direction)),
      ];
      return $this;
   }

   /**
    * Limit the number of rows returned.
    *
    * @param int $limit
    * @return $this
    */
   public function limit(int $limit): self
   {
      $this->limit = max(0, $limit);
      return $this;
   }

   /**
    * Skip the first N rows.
    *
    * @param int $offset
    * @return $this
    */
   public function offset(int $offset): self
   {
      $this->offset = max(0, $offset);
      return $this;
   }

   /**
    * Execute and return the first row or null.
    *
    * @return ?TModel
    */
   public function first(): ?ActiveRecordModel
   {
      $results = $this->manager->fetchByQuery(
         $this->modelClass,
         $this->conditions,
         $this->orders,
         1,
         $this->offset,
      );

      return $results[0] ?? null;
   }

   /**
    * Execute and return all matching rows.
    *
    * @return array<int, TModel>
    */
   public function get(): array
   {
      return $this->manager->fetchByQuery(
         $this->modelClass,
         $this->conditions,
         $this->orders,
         $this->limit,
         $this->offset,
      );
   }

   /**
    * Execute query and return number of hydrated rows.
    *
    * @return int
    */
   public function count(): int
   {
      return count($this->get());
   }
}
