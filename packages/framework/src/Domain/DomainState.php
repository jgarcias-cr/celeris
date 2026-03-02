<?php

declare(strict_types=1);

namespace Celeris\Framework\Domain;

/**
 * Model the allowed domain state values used by Domain logic.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
enum DomainState: string
{
   case DRAFT = 'draft';
   case ACTIVE = 'active';
   case SUSPENDED = 'suspended';
   case ARCHIVED = 'archived';
   case DELETED = 'deleted';
}


