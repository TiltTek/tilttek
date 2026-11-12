<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\League;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * A member's place in a league for one week: the tier, the division of that tier — numbered from 1 —, when they
 * joined it, the cohort of activity they were placed by — 0 when the divisions are formed by arrival —, and, once the
 * week is settled, where they went.
 */
final readonly class Membership
{
    public function __construct(
        public string $board,
        public string $week,
        public ResourceReference $member,
        public string $tier,
        public int $division,
        public DateTimeImmutable $joinedAt,
        public ?Movement $movement = null,
        public int $cohort = League::NO_COHORT,
    ) {
        if ($division < 1) {
            throw InvalidLeaderboard::because(\sprintf('Divisions are numbered from 1; %d is not one.', $division));
        }

        if ($cohort < 0 || $cohort > 64) {
            throw InvalidLeaderboard::because(\sprintf('A cohort is 0 to 64; %d is not one.', $cohort));
        }
    }

    public function settled(Movement $movement): self
    {
        return new self($this->board, $this->week, $this->member, $this->tier, $this->division, $this->joinedAt, $movement, $this->cohort);
    }

    /**
     * Whether the other membership is in the same division: the same board, week, tier and number.
     */
    public function sharesDivisionWith(self $other): bool
    {
        return [$this->board, $this->week, $this->tier, $this->division] === [$other->board, $other->week, $other->tier, $other->division];
    }

    /**
     * @return array{board: string, week: string, tier: string, division: int, cohort: int, joined_at: string, movement: ?string}
     */
    public function toArray(): array
    {
        return ['board' => $this->board, 'week' => $this->week, 'tier' => $this->tier, 'division' => $this->division, 'cohort' => $this->cohort, 'joined_at' => $this->joinedAt->format(DateTimeInterface::ATOM), 'movement' => $this->movement?->value];
    }
}
