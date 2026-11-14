<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Exception;

use DomainException;

/**
 * A move the rules of the game refuse at this moment: an answer after the time, a second answer to a question, a
 * guest outside a live session, a session that is over, or a quiz, a run or a session the stores do not know.
 */
final class QuizRefused extends DomainException implements QuizException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function unknownQuiz(string $quiz): self
    {
        return new self(\sprintf('No quiz has the identifier %s.', $quiz));
    }

    public static function unknownPlay(string $play): self
    {
        return new self(\sprintf('No play has the identifier %s.', $play));
    }

    public static function unknownSession(string $session): self
    {
        return new self(\sprintf('No live session has the identifier %s.', $session));
    }
}
