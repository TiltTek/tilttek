<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * A member's line in a snapshot: their rank, their score and when they reached it, frozen at the end of the period.
 */
final readonly class Placing
{
    public function __construct(
        public string $board,
        public string $period,
        public ResourceReference $member,
        public int $rank,
        public int $value,
        public DateTimeImmutable $reachedAt,
    ) {
    }

    /**
     * @return array{board: string, period: string, rank: int, value: int, reached_at: string}
     */
    public function toArray(): array
    {
        return ['board' => $this->board, 'period' => $this->period, 'rank' => $this->rank, 'value' => $this->value, 'reached_at' => $this->reachedAt->format(DateTimeInterface::ATOM)];
    }
}
