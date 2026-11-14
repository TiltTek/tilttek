<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Exception;

use RuntimeException;

/**
 * A play or a live session changed in the store since it was read: the change was made from a version that is no
 * longer the stored one, or a play was started at an attempt another play of the member took first. The services read
 * it again and retry a few times before letting this through.
 */
final class QuizConflict extends RuntimeException implements QuizException
{
    public static function at(string $what, string $id, int $expected, ?int $stored): self
    {
        return new self(null === $stored
            ? \sprintf('The %s %s is not stored.', $what, $id)
            : \sprintf('The %s %s is at version %d, not %d.', $what, $id, $stored, $expected));
    }

    public static function attempt(string $quiz, string $player, int $attempt): self
    {
        return new self(\sprintf('The player %s has a play of the quiz %s at attempt %d already.', $player, $quiz, $attempt));
    }
}
