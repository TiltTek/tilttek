<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\League;

use DateTimeImmutable;

/**
 * The end of a week of a league, settled once: every membership of the week with where its member went, and when.
 */
final readonly class Settlement
{
    /**
     * @param list<Membership> $memberships by tier name, by division, then in rank order, each with its movement
     */
    public function __construct(
        public string $board,
        public string $week,
        public DateTimeImmutable $settledAt,
        public array $memberships,
    ) {
    }

    /**
     * The memberships whose member went that way.
     *
     * @return list<Membership>
     */
    public function moved(Movement $movement): array
    {
        return array_values(array_filter($this->memberships, static fn (Membership $membership): bool => $membership->movement === $movement));
    }
}
