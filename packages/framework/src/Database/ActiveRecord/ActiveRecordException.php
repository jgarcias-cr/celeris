<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord;

use Celeris\Framework\Database\ORM\OrmException;

/**
 * Represent failures raised by the optional Active Record compatibility layer.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
class ActiveRecordException extends OrmException
{
}
