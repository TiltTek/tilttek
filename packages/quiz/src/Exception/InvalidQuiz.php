<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: an identifier, a question, an answer of the wrong kind, a pseudonym or a scoring rule
 * it cannot take.
 */
final class InvalidQuiz extends InvalidArgumentException implements QuizException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
