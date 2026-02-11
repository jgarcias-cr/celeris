<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

use Celeris\Framework\Database\Connection\ConnectionInterface;

/**
 * Purpose: define the contract for migration interface behavior in the Database subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete database services and resolved via dependency injection.
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



