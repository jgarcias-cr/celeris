<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Intent;

/**
 * Model the allowed cache intent type values used by Cache logic.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
enum CacheIntentType: string
{
   case ReadThrough = 'read_through';
   case WriteThrough = 'write_through';
   case Invalidate = 'invalidate';
}


