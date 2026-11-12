<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * What one activity brought a member on a board, in the period it fell in: one for a board that counts gestures, the
 * activity's quantity for one that adds them up or keeps the best, with what it brings to each tie-break. It is kept so that a cancellation or a rebuild can
 * take it back.
 */
final readonly class Contribution
{
    /**
     * @param list<int> $tieBreaks
     */
    public function __construct(
        public string $board,
        public string $period,
        public ResourceReference $member,
        public string $activity,
        public int $amount,
        public DateTimeImmutable $occurredAt,
        public array $tieBreaks = [],
    ) {
    }
}
