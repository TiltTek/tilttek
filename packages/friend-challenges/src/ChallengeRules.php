<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use Tilttek\FriendChallenges\Exception\InvalidChallenge;

/**
 * The site's bounds on the challenges its members create: how long a challenge lasts, how long its proofs stay open
 * to review once it ends, and the largest prize. Times are in seconds.
 */
final readonly class ChallengeRules
{
    public const int HOUR = 3600;

    public const int DAY = 86_400;

    public function __construct(
        public int $shortest = self::HOUR,
        public int $longest = 30 * self::DAY,
        public int $review = 3 * self::DAY,
        public int $largestPrize = 10_000,
    ) {
        if ($shortest < 60 || $longest < $shortest || $longest > 366 * self::DAY) {
            throw InvalidChallenge::because(\sprintf('A challenge lasts from a minute to a year, the shortest no longer than the longest; %d to %d seconds is not that.', $shortest, $longest));
        }

        if ($review < 0 || $review > 30 * self::DAY) {
            throw InvalidChallenge::because(\sprintf('The review of the proofs lasts 0 to 30 days; %d seconds is not that.', $review));
        }

        if ($largestPrize < 1 || $largestPrize > Prize::MAX_AMOUNT) {
            throw InvalidChallenge::because(\sprintf('The largest prize is 1 to %d points; %d is not.', Prize::MAX_AMOUNT, $largestPrize));
        }
    }
}
