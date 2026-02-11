<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Intent;

/**
 * Purpose: model the allowed cache intent type values used by Cache logic.
 * How: uses native enum cases to keep branching and serialization type-safe and explicit.
 * Used in framework: referenced by cache logic, serialization, and guard conditions.
 */
enum CacheIntentType: string
{
   case ReadThrough = 'read_through';
   case WriteThrough = 'write_through';
   case Invalidate = 'invalidate';
}


