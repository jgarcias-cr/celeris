<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

use Celeris\Framework\Database\Connection\ConnectionInterface;

/**
 * Purpose: implement sql migration behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when sql migration functionality is required.
 */
abstract class SqlMigration implements MigrationInterface
{
   /** @var array<int, string> */
   private array $upSql = [];
   /** @var array<int, string> */
   private array $downSql = [];

   /**
    * Handle up.
    *
    * @param ConnectionInterface $connection
    * @return void
    */
   final public function up(ConnectionInterface $connection): void
   {
      $this->upSql = [];
      $this->buildUp();
      foreach ($this->upSql as $sql) {
         $connection->execute($sql);
      }
   }

   /**
    * Handle down.
    *
    * @param ConnectionInterface $connection
    * @return void
    */
   final public function down(ConnectionInterface $connection): void
   {
      $this->downSql = [];
      $this->buildDown();
      foreach ($this->downSql as $sql) {
         $connection->execute($sql);
      }
   }

   /**
    * Handle build up.
    *
    * @return void
    */
   abstract protected function buildUp(): void;

   /**
    * Handle build down.
    *
    * @return void
    */
   abstract protected function buildDown(): void;

   /**
    * Handle add sql.
    *
    * @param string $sql
    * @return void
    */
   protected function addSql(string $sql): void
   {
      $trimmed = trim($sql);
      if ($trimmed === '') {
         return;
      }

      $this->upSql[] = $trimmed;
   }

   /**
    * Handle add down sql.
    *
    * @param string $sql
    * @return void
    */
   protected function addDownSql(string $sql): void
   {
      $trimmed = trim($sql);
      if ($trimmed === '') {
         return;
      }

      $this->downSql[] = $trimmed;
   }
}



