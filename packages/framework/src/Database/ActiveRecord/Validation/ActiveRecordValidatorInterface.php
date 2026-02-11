<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Validation;

use Celeris\Framework\Database\ActiveRecord\ActiveRecordModel;
use Celeris\Framework\Database\ORM\EntityMetadata;

/**
 * Purpose: define Active Record validation behavior before persistence.
 * How: accepts a model plus its metadata and returns a normalized validation result.
 * Used in framework: called by Active Record manager to enforce constraints consistently.
 */
interface ActiveRecordValidatorInterface
{
   /**
    * Validate a model instance against metadata and custom rules.
    *
    * @param ActiveRecordModel $model
    * @param EntityMetadata $metadata
    * @return ActiveRecordValidationResult
    */
   public function validate(ActiveRecordModel $model, EntityMetadata $metadata): ActiveRecordValidationResult;
}
