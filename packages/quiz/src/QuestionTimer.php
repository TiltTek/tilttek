<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use DateTimeImmutable;

/**
 * The time of a question: when it was shown and how many seconds it lasts, if any. It reads the moments the clock of
 * the game gives, in milliseconds, and never the clock of the system.
 */
final readonly class QuestionTimer
{
    public function __construct(
        public DateTimeImmutable $shownAt,
        public ?int $limit,
    ) {
    }

    /**
     * The milliseconds since the question was shown; nothing for a moment before it.
     */
    public function elapsed(DateTimeImmutable $now): int
    {
        return max(0, $this->milliseconds($now) - $this->milliseconds($this->shownAt));
    }

    /**
     * The milliseconds left, or null for an untimed question; nothing once the time is up.
     */
    public function remaining(DateTimeImmutable $now): ?int
    {
        if (null === $this->limit) {
            return null;
        }

        return max(0, $this->limit * 1000 - $this->elapsed($now));
    }

    /**
     * Whether the time is up: an untimed question never runs out; a timed one runs out at its last millisecond.
     */
    public function isOver(DateTimeImmutable $now): bool
    {
        return null !== $this->limit && $this->elapsed($now) > $this->limit * 1000;
    }

    /**
     * The moment the time is up, or null for an untimed question.
     */
    public function deadline(): ?DateTimeImmutable
    {
        return null === $this->limit ? null : $this->shownAt->modify(\sprintf('+%d seconds', $this->limit));
    }

    private function milliseconds(DateTimeImmutable $moment): int
    {
        return (int) $moment->format('Uv');
    }
}
