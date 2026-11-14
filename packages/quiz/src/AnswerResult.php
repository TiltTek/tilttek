<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

/**
 * What an answer earned: its grade, its points, its speed bonus and the milliseconds it took. A question left without
 * an answer when its time ran out has a result too, wrong and worth nothing, marked `timedOut`.
 */
final readonly class AnswerResult
{
    public function __construct(
        public string $question,
        public Grade $grade,
        public int $points,
        public int $bonus,
        public int $elapsed,
        public bool $timedOut = false,
    ) {
    }

    public static function timedOut(string $question, int $elapsed): self
    {
        return new self($question, Grade::wrong(), 0, 0, $elapsed, true);
    }

    public function total(): int
    {
        return $this->points + $this->bonus;
    }
}
