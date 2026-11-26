<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Exception;

use DomainException;
use Tilttek\Predictions\QuestionKey;

/**
 * What a question no longer allows: a prediction once it is closed — its deadline passed, resolved or cancelled —, a
 * cancellation once it is resolved, a resolution once it is cancelled or resolved with another outcome, a settlement
 * before it is resolved.
 */
final class QuestionClosed extends DomainException implements PredictionsException
{
    public static function toPredictions(QuestionKey $key): self
    {
        return new self(\sprintf('The question "%s" takes no more predictions.', $key->value));
    }

    public static function resolved(QuestionKey $key): self
    {
        return new self(\sprintf('The question "%s" is resolved: it can no longer be cancelled or resolved otherwise.', $key->value));
    }

    public static function cancelled(QuestionKey $key): self
    {
        return new self(\sprintf('The question "%s" is cancelled: it can no longer be resolved.', $key->value));
    }

    public static function unresolved(QuestionKey $key): self
    {
        return new self(\sprintf('The question "%s" is not resolved yet: there is nothing to settle.', $key->value));
    }
}
