<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use Tilttek\Bounties\Exception\InvalidBounty;

/**
 * The site's bounds on the bounties its members offer: how long a bounty stays open to claims — a bounded length is
 * required —, the grace after its end during which its sponsor still awards it, and its smallest and largest amount.
 * Times are in seconds.
 */
final readonly class BountyRules
{
    public const int DAY = 86_400;

    public const int MAX_OPEN_PER_SPONSOR = 100;

    public function __construct(
        public int $shortest = self::DAY,
        public int $longest = 30 * self::DAY,
        public int $grace = self::DAY,
        public int $smallestPrize = 10,
        public int $largestPrize = 10_000,
        public int $openPerSponsor = 5,
    ) {
        if ($shortest < 3600 || $longest < $shortest || $longest > 90 * self::DAY) {
            throw InvalidBounty::because(\sprintf('A bounty lasts from an hour to ninety days, the shortest no longer than the longest; %d to %d seconds is not that.', $shortest, $longest));
        }

        if ($grace < 0 || $grace > 7 * self::DAY) {
            throw InvalidBounty::because(\sprintf('The grace after a bounty lasts 0 to 7 days; %d seconds is not that.', $grace));
        }

        if ($smallestPrize < 1 || $largestPrize < $smallestPrize || $largestPrize > Prize::MAX_AMOUNT) {
            throw InvalidBounty::because(\sprintf('A bounty is 1 to %d points, the smallest no larger than the largest; %d to %d is not that.', Prize::MAX_AMOUNT, $smallestPrize, $largestPrize));
        }

        if ($openPerSponsor < 1 || $openPerSponsor > self::MAX_OPEN_PER_SPONSOR) {
            throw InvalidBounty::because(\sprintf('A member sponsors 1 to %d open bounties at once; %d is not that.', self::MAX_OPEN_PER_SPONSOR, $openPerSponsor));
        }
    }
}
