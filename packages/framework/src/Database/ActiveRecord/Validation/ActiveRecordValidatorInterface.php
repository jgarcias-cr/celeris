<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Validation;

use Celeris\Framework\Database\ActiveRecord\ActiveRecordModel;
use Celeris\Framework\Database\ORM\EntityMetadata;

/**
 * Define Active Record validation behavior before persistence.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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
