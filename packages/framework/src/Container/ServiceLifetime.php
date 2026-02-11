<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: model the allowed service lifetime values used by Container logic.
 * How: uses native enum cases to keep branching and serialization type-safe and explicit.
 * Used in framework: referenced by container logic, serialization, and guard conditions.
 */
enum ServiceLifetime: string
{
   case Singleton = 'singleton';
   case Request = 'request';
   case Transient = 'transient';
}



