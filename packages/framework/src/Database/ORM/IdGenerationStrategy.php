<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

use Celeris\Framework\Database\DatabaseException;

/**
 * Define supported identifier generation strategies for ORM primary keys.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
enum IdGenerationStrategy: string
{
   case Auto = 'auto';
   case Identity = 'identity';
   case Sequence = 'sequence';
   case None = 'none';

   /**
    * Resolve an identifier generation strategy from its string representation.
    */
   public static function fromString(string $value): self
   {
      $normalized = strtolower(trim($value));

      return match ($normalized) {
         'auto' => self::Auto,
         'identity', 'increment', 'autoincrement' => self::Identity,
         'sequence', 'seq' => self::Sequence,
         'none', 'manual' => self::None,
         default => throw new DatabaseException(sprintf('Unsupported id generation strategy "%s".', $value)),
      };
   }
}

