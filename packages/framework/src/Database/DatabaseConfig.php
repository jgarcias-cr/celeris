<?php

declare(strict_types=1);

namespace Celeris\Framework\Database;

/**
 * Purpose: implement database config behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when database config functionality is required.
 */
final class DatabaseConfig
{
   /** @var array<string, int|string|bool|null> */
   private array $options;

   /**
    * @param array<string, int|string|bool|null> $options
    */
   public function __construct(
      private string $name,
      private DatabaseDriver $driver,
      private ?string $dsn = null,
      private ?string $host = null,
      private ?int $port = null,
      private ?string $database = null,
      private ?string $username = null,
      private ?string $password = null,
      private ?string $charset = 'utf8mb4',
      private ?string $path = null,
      array $options = [],
   ) {
      $this->options = $options;
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return $this->name;
   }

   /**
    * Handle driver.
    *
    * @return DatabaseDriver
    */
   public function driver(): DatabaseDriver
   {
      return $this->driver;
   }

   /**
    * Handle dsn.
    *
    * @return ?string
    */
   public function dsn(): ?string
   {
      return $this->dsn;
   }

   /**
    * Handle host.
    *
    * @return ?string
    */
   public function host(): ?string
   {
      return $this->host;
   }

   /**
    * Handle port.
    *
    * @return ?int
    */
   public function port(): ?int
   {
      return $this->port;
   }

   /**
    * Handle database.
    *
    * @return ?string
    */
   public function database(): ?string
   {
      return $this->database;
   }

   /**
    * Handle username.
    *
    * @return ?string
    */
   public function username(): ?string
   {
      return $this->username;
   }

   /**
    * Handle password.
    *
    * @return ?string
    */
   public function password(): ?string
   {
      return $this->password;
   }

   /**
    * Handle charset.
    *
    * @return ?string
    */
   public function charset(): ?string
   {
      return $this->charset;
   }

   /**
    * Handle path.
    *
    * @return ?string
    */
   public function path(): ?string
   {
      return $this->path;
   }

   /**
    * @return array<string, int|string|bool|null>
    */
   public function options(): array
   {
      return $this->options;
   }

   /**
    * @param array<string, mixed> $spec
    */
   public static function fromArray(string $name, array $spec): self
   {
      $driver = DatabaseDriver::fromString((string) ($spec['driver'] ?? 'sqlite'));

      $options = [];
      $rawOptions = $spec['options'] ?? [];
      if (is_array($rawOptions)) {
         foreach ($rawOptions as $key => $value) {
            if (is_int($value) || is_string($value) || is_bool($value) || $value === null) {
               $options[(string) $key] = $value;
            }
         }
      }

      return new self(
         $name,
         $driver,
         self::nullableString($spec['dsn'] ?? null),
         self::nullableString($spec['host'] ?? null),
         isset($spec['port']) && is_numeric($spec['port']) ? (int) $spec['port'] : null,
         self::nullableString($spec['database'] ?? null),
         self::nullableString($spec['username'] ?? null),
         self::nullableString($spec['password'] ?? null),
         self::nullableString($spec['charset'] ?? 'utf8mb4'),
         self::nullableString($spec['path'] ?? null),
         $options,
      );
   }

   /**
    * Handle nullable string.
    *
    * @param mixed $value
    * @return ?string
    */
   private static function nullableString(mixed $value): ?string
   {
      if (!is_string($value)) {
         return null;
      }

      $clean = trim($value);
      return $clean === '' ? null : $clean;
   }
}


