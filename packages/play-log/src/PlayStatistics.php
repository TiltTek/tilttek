<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

use DateTimeImmutable;
use Tilttek\PlayLog\Contract\PlayStore;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Internal\Tally;

/**
 * The statistics of a member or of a group, computed when asked from the plays the store gives, never kept: at most
 * 10 000 plays are read, the latest first, and the result says when more matched. It keeps no state between calls.
 */
final readonly class PlayStatistics
{
    /** The most plays read for one result. */
    public const int MAX_PLAYS = 10_000;

    /** The most members a result names. */
    public const int MAX_PLAYERS = 50;

    public function __construct(private PlayStore $store)
    {
    }

    /**
     * The plays where the member holds a confirmed seat, from the first moment, included, to the second, excluded;
     * either may be open, and a period leaves out the plays whose date is unknown.
     *
     * @throws InvalidPlay when the member is not an identifier, or the period ends before it starts
     */
    public function ofMember(string $member, ?DateTimeImmutable $from = null, ?DateTimeImmutable $until = null): Statistics
    {
        return $this->compute(PlayQuery::ofMember($member)->between($from, $until), $member);
    }

    /**
     * The plays of the group, read as `ofMember()` reads a member's.
     *
     * @throws InvalidPlay when the group is not an identifier, or the period ends before it starts
     */
    public function ofGroup(string $group, ?DateTimeImmutable $from = null, ?DateTimeImmutable $until = null): Statistics
    {
        return $this->compute(PlayQuery::ofGroup($group)->between($from, $until), null);
    }

    private function compute(PlayQuery $query, ?string $member): Statistics
    {
        $tally = new Tally($member);
        $read = 0;

        foreach ($this->store->plays($query, self::MAX_PLAYS + 1) as $play) {
            if ($read >= self::MAX_PLAYS) {
                return $tally->statistics(self::MAX_PLAYERS, true);
            }

            $tally->add($play);
            ++$read;
        }

        return $tally->statistics(self::MAX_PLAYERS, false);
    }
}
