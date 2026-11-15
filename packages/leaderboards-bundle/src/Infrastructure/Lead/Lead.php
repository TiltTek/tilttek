<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Lead;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * A member who took the lead of a board in a period — the board's key, with its object's digest on a board ranked by
 * object —, and the moment they reached the score that put them there. Kept once per board, period and member.
 */
final readonly class Lead
{
    public function __construct(
        public string $board,
        public string $period,
        public ResourceReference $member,
        public DateTimeImmutable $takenAt,
    ) {
    }

    /**
     * @return array{board: string, period: string, taken_at: string}
     */
    public function toArray(): array
    {
        return ['board' => $this->board, 'period' => $this->period, 'taken_at' => $this->takenAt->format(DateTimeInterface::ATOM)];
    }
}
