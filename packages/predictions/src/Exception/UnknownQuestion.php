<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Exception;

use DomainException;
use Tilttek\Predictions\QuestionKey;

/**
 * No question is asked under the key.
 */
final class UnknownQuestion extends DomainException implements PredictionsException
{
    public static function named(QuestionKey $key): self
    {
        return new self(\sprintf('No question is asked under the key "%s".', $key->value));
    }
}
