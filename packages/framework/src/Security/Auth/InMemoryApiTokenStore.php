<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Purpose: implement in memory api token store behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when in memory api token store functionality is required.
 */
final class InMemoryApiTokenStore implements ApiTokenStoreInterface
{
   /** @var array<string, StoredCredential> */
   private array $tokens = [];

   /**
    * @param array<string, StoredCredential> $tokens
    */
   public function __construct(array $tokens = [])
   {
      foreach ($tokens as $token => $credential) {
         $this->set($token, $credential);
      }
   }

   /**
    * Handle find.
    *
    * @param string $token
    * @return ?StoredCredential
    */
   public function find(string $token): ?StoredCredential
   {
      return $this->tokens[$token] ?? null;
   }

   /**
    * Set the value.
    *
    * @param string $token
    * @param StoredCredential $credential
    * @return void
    */
   public function set(string $token, StoredCredential $credential): void
   {
      $clean = trim($token);
      if ($clean === '') {
         return;
      }

      $this->tokens[$clean] = $credential;
   }

   /**
    * Handle remove.
    *
    * @param string $token
    * @return void
    */
   public function remove(string $token): void
   {
      unset($this->tokens[$token]);
   }
}



