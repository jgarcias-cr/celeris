<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Password;

use RuntimeException;

/**
 * Implement password hasher behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class PasswordHasher
{
   /** @var array<string, int> */
   private array $options;
   private string|int|null $algorithm;

   /**
    * @param array<string, int> $options
    */
   public function __construct(?string $algorithm = null, array $options = [])
   {
      $resolvedAlgorithm = $algorithm;
      if ($resolvedAlgorithm === null || trim($resolvedAlgorithm) === '') {
         $resolvedAlgorithm = defined('PASSWORD_ARGON2ID') ? (string) PASSWORD_ARGON2ID : (string) PASSWORD_BCRYPT;
      }

      $this->algorithm = match (strtolower(trim($resolvedAlgorithm))) {
         'argon2id' => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
         'bcrypt' => PASSWORD_BCRYPT,
         default => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
      };
      if ($options !== []) {
         $this->options = $options;
      } else {
         $this->options = $this->algorithm === PASSWORD_BCRYPT
            ? ['cost' => 12]
            : ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];
      }
   }

   /**
    * Determine whether hash.
    *
    * @param string $plainText
    * @return string
    */
   public function hash(string $plainText): string
   {
      if ($plainText === '' || strlen($plainText) > 4096) {
         throw new RuntimeException('Password length is invalid.');
      }

      $hash = password_hash($plainText, $this->algorithm, $this->options);
      if (!is_string($hash) || $hash === '') {
         throw new RuntimeException('Password hashing failed.');
      }

      return $hash;
   }

   /**
    * Handle verify.
    *
    * @param string $plainText
    * @param string $hash
    * @return bool
    */
   public function verify(string $plainText, string $hash): bool
   {
      return password_verify($plainText, $hash);
   }

   /**
    * Handle needs rehash.
    *
    * @param string $hash
    * @return bool
    */
   public function needsRehash(string $hash): bool
   {
      return password_needs_rehash($hash, $this->algorithm, $this->options);
   }
}



