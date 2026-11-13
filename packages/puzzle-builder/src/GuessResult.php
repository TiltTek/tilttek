<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

use DateTimeImmutable;

/**
 * What a guess gave, and the progress to store after it. It never carries the answer.
 */
final readonly class GuessResult
{
    /**
     * @param ?string            $message    the message of a partial answer
     * @param ?DateTimeImmutable $retryAfter when a throttled team may guess again
     * @param list<string>       $opened     the puzzles this guess opened, in the order of the scenario
     * @param bool               $completed  whether this guess ended the scenario
     */
    public function __construct(
        public GuessOutcome $outcome,
        public Progress $progress,
        public ?string $message = null,
        public ?DateTimeImmutable $retryAfter = null,
        public array $opened = [],
        public bool $completed = false,
    ) {
    }

    public function solved(): bool
    {
        return GuessOutcome::Correct === $this->outcome;
    }
}
