<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Exception;

use Celeris\Framework\Database\ActiveRecord\ActiveRecordException;

/**
 * Purpose: signal that an Active Record lookup expected a row but none matched the given identifier.
 * How: carries model class and primary key details in a deterministic message.
 * Used in framework: raised by `findOrFail` APIs in Active Record manager/model components.
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
