<?php

declare(strict_types=1);

namespace Tilttek\TurnClock;

use DateTimeImmutable;
use DateTimeInterface;
use Tilttek\TurnClock\Exception\InvalidClock;
use Tilttek\TurnClock\Internal\Millis;

/**
 * A vacation a player declared: while it lasts, their clock does not run.
 */
final readonly class Vacation
{
    /** The longest vacation: 60 days. */
    public const int MAX_LENGTH = 60 * 86_400_000;

    public DateTimeImmutable $from;

    public DateTimeImmutable $until;

    /**
     * @throws InvalidClock when it does not end after it starts, or lasts more than 60 days
     */
    public function __construct(DateTimeInterface $from, DateTimeInterface $until)
    {
        $length = Millis::of($until) - Millis::of($from);
        if ($length <= 0 || $length > self::MAX_LENGTH) {
            throw InvalidClock::because('A vacation ends after it starts, at most 60 days later.');
        }

        $this->from = DateTimeImmutable::createFromInterface($from);
        $this->until = DateTimeImmutable::createFromInterface($until);
    }

    /**
     * The milliseconds of the vacation within a span.
     */
    public function overlap(int $from, int $until): int
    {
        return max(0, min($until, Millis::of($this->until)) - max($from, Millis::of($this->from)));
    }
}
