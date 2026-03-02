<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Exception;

use Celeris\Framework\Database\ActiveRecord\ActiveRecordException;

/**
 * Signal that an Active Record lookup expected a row but none matched the given identifier.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ModelNotFoundException extends ActiveRecordException
{
   /**
    * Build an exception describing the missing model and identifier.
    *
    * @param string $modelClass
    * @param string|int $id
    * @return self
    */
   public static function forId(string $modelClass, string|int $id): self
   {
      return new self(sprintf('Model "%s" with id "%s" was not found.', $modelClass, (string) $id));
   }
}
