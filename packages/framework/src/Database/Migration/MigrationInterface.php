<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

use Celeris\Framework\Database\Connection\ConnectionInterface;

/**
 * Define the contract for migration interface behavior in the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface MigrationInterface
{
   /**
    * Handle version.
    *
    * @return string
    */
   public function version(): string;

   /**
    * Handle description.
    *
    * @return string
    */
   public function description(): string;

   /**
    * Handle up.
    *
    * @param ConnectionInterface $connection
    * @return void
    */
   public function up(ConnectionInterface $connection): void;

   /**
    * Handle down.
    *
    * @param ConnectionInterface $connection
    * @return void
    */
   public function down(ConnectionInterface $connection): void;
}



