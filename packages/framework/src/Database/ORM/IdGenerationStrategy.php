<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

use Celeris\Framework\Database\DatabaseException;

/**
 * Purpose: define supported identifier generation strategies for ORM primary keys.
 * How: provides normalized enum values and strict parsing from config/attributes.
 * Used in framework: consumed by metadata and EntityManager insert workflows.
 */
enum IdGenerationStrategy: string
{
   case Auto = 'auto';
   case Identity = 'identity';
   case Sequence = 'sequence';
   case None = 'none';

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

