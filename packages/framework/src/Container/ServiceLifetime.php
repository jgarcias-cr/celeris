<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Model the allowed service lifetime values used by Container logic.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
enum ServiceLifetime: string
{
   case Singleton = 'singleton';
   case Request = 'request';
   case Transient = 'transient';
}



