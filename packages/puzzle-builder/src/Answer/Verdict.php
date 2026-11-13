<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Answer;

use Tilttek\PuzzleBuilder\GuessOutcome;

/**
 * What an answer check says of a guess: correct, partial with the message to show, or wrong. It never carries the
 * answer.
 */
final readonly class Verdict
{
    private function __construct(
        public GuessOutcome $outcome,
        public ?string $message = null,
    ) {
    }

    public static function correct(): self
    {
        return new self(GuessOutcome::Correct);
    }

    /**
     * @param string $message what the players read: "Keep going", "You found the first word"
     */
    public static function partial(string $message): self
    {
        return new self(GuessOutcome::Partial, $message);
    }

    public static function wrong(): self
    {
        return new self(GuessOutcome::Wrong);
    }
}
