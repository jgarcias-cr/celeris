<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Purpose: implement in memory session store behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when in memory session store functionality is required.
 */
final class InMemorySessionStore implements SessionStoreInterface
{
   /** @var array<string, StoredCredential> */
   private array $sessions = [];

   /**
    * @param array<string, StoredCredential> $sessions
    */
   public function __construct(array $sessions = [])
   {
      foreach ($sessions as $sessionId => $credential) {
         $this->set($sessionId, $credential);
      }
   }

   /**
    * Handle find.
    *
    * @param string $sessionId
    * @return ?StoredCredential
    */
   public function find(string $sessionId): ?StoredCredential
   {
      return $this->sessions[$sessionId] ?? null;
   }

   /**
    * Set the value.
    *
    * @param string $sessionId
    * @param StoredCredential $credential
    * @return void
    */
   public function set(string $sessionId, StoredCredential $credential): void
   {
      $clean = trim($sessionId);
      if ($clean === '') {
         return;
      }

      $this->sessions[$clean] = $credential;
   }

   /**
    * Handle remove.
    *
    * @param string $sessionId
    * @return void
    */
   public function remove(string $sessionId): void
   {
      unset($this->sessions[$sessionId]);
   }
}



