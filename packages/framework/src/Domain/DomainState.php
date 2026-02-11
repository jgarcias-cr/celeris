<?php

declare(strict_types=1);

namespace Celeris\Framework\Domain;

/**
 * Purpose: model the allowed domain state values used by Domain logic.
 * How: uses native enum cases to keep branching and serialization type-safe and explicit.
 * Used in framework: referenced by domain logic, serialization, and guard conditions.
 */
enum DomainState: string
{
   case DRAFT = 'draft';
   case ACTIVE = 'active';
   case SUSPENDED = 'suspended';
   case ARCHIVED = 'archived';
   case DELETED = 'deleted';
}


