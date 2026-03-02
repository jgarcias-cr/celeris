<?php

declare(strict_types=1);

namespace Celeris\Framework\Database;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Database\Connection\ConnectionPool;
use Celeris\Framework\Database\Connection\PdoConnectionFactory;
use Celeris\Framework\Database\Testing\ArrayConnection;

/**
 * Implement database bootstrap behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class DatabaseBootstrap
{
   /**
    * Handle pool from config.
    *
    * @param ConfigRepository $config
    * @return ConnectionPool
    */
   public static function poolFromConfig(ConfigRepository $config): ConnectionPool
   {
      $connectionsSpec = $config->get('database.connections', []);

      $configs = [];
      if (is_array($connectionsSpec)) {
         foreach ($connectionsSpec as $name => $spec) {
            if (!is_array($spec)) {
               continue;
            }

            $configs[(string) $name] = DatabaseConfig::fromArray((string) $name, $spec);
         }
      }

      $pool = new ConnectionPool($configs, new PdoConnectionFactory());

      if ($configs === []) {
         $pool->addConnection('default', new ArrayConnection('default'));
      }

      return $pool;
   }

   /**
    * Handle default connection name.
    *
    * @param ConfigRepository $config
    * @return string
    */
   public static function defaultConnectionName(ConfigRepository $config): string
   {
      $name = $config->get('database.default', 'default');
      return is_string($name) && trim($name) !== '' ? trim($name) : 'default';
   }
}



