<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord;

use Celeris\Framework\Database\ORM\OrmException;

/**
 * Purpose: represent failures raised by the optional Active Record compatibility layer.
 * How: extends ORM exceptions so AR-specific errors can be handled without affecting mapper-style flows.
 * Used in framework: thrown by Active Record model, manager, query, and validation components.
 */
class ActiveRecordException extends OrmException
{
}
