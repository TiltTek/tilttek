<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use DateTimeImmutable;
use Tilttek\Sets\Exception\InvalidSet;

/**
 * How long `Sets::deliverPending()` waits before telling again a holder whose delivery failed: the first delay after
 * the first failure, doubled after each failure that follows, never above the longest delay. Past `$reportAfter`
 * failures in a row, each failure is also told by a `DeliveryKeepsFailing` event.
 */
final readonly class DeliveryBackoff
{
    /** The delay after a first failure, in seconds, unless the application chooses otherwise: one minute. */
    public const int FIRST_DELAY = 60;

    /** The longest delay, in seconds, unless the application chooses otherwise: one day. */
    public const int LONGEST_DELAY = 86_400;

    /** The failures in a row from which each one is reported, unless the application chooses otherwise. */
    public const int REPORT_AFTER = 3;

    /**
     * @param int $firstDelay   the delay after a first failure, in seconds, 1 or more
     * @param int $longestDelay the longest delay, in seconds, not under the first
     * @param int $reportAfter  the failures in a row from which each one is reported, 1 or more
     *
     * @throws InvalidSet when a delay or the number of failures is out of bounds
     */
    public function __construct(
        public int $firstDelay = self::FIRST_DELAY,
        public int $longestDelay = self::LONGEST_DELAY,
        public int $reportAfter = self::REPORT_AFTER,
    ) {
        if ($firstDelay < 1 || $longestDelay < $firstDelay) {
            throw InvalidSet::because(\sprintf('A delivery waits at least 1 second and the longest delay is not under the first; %d and %d are not.', $firstDelay, $longestDelay));
        }

        if ($reportAfter < 1) {
            throw InvalidSet::because(\sprintf('Failures are reported from the first one at the earliest; %d is not.', $reportAfter));
        }
    }

    /**
     * The delay, in seconds, after the given number of failures in a row.
     */
    public function delay(int $failures): int
    {
        $delay = $this->firstDelay;

        for ($failure = 1; $failure < $failures && $delay < $this->longestDelay; ++$failure) {
            $delay *= 2;
        }

        return min($delay, $this->longestDelay);
    }

    /**
     * The moment a holder is told again, after the given number of failures in a row.
     */
    public function nextAttempt(int $failures, DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify(\sprintf('+%d seconds', $this->delay($failures)));
    }

    /**
     * Whether a failure, the given number in a row, is reported.
     */
    public function reports(int $failures): bool
    {
        return $failures >= $this->reportAfter;
    }
}
