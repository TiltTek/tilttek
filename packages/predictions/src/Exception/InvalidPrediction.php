<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a key, an outcome, a pick or a number of points out of bounds, a pick that does not fit
 * its question, a question asked again differently, a read out of bounds.
 */
final class InvalidPrediction extends InvalidArgumentException implements PredictionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
