<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

use DateInterval;
use DateTimeImmutable;
use Tilttek\Kudos\Exception\InvalidKudos;

/**
 * How much a member may give: thanks per day, counted in their time zone — five by default —, and thanks to the same
 * member per period — three in seven days by default —, which keeps two members from thanking each other in a loop.
 * Every thanks given counts, withdrawn or not.
 *
 * Each count is 1 to 100; the period lasts one hour to 366 days.
 */
final readonly class Limits
{
    public const int DEFAULT_DAILY = 5;

    public const int DEFAULT_PER_RECEIVER = 3;

    /**
     * The period of the limit per receiver, by default, as an ISO 8601 duration.
     */
    public const string DEFAULT_PERIOD = 'P7D';

    public const int MAX_COUNT = 100;

    public function __construct(
        public int $daily = self::DEFAULT_DAILY,
        public int $perReceiver = self::DEFAULT_PER_RECEIVER,
        public DateInterval $period = new DateInterval(self::DEFAULT_PERIOD),
    ) {
        if ($daily < 1 || $daily > self::MAX_COUNT) {
            throw InvalidKudos::because(\sprintf('The daily cap is 1 to %d thanks; it is %d.', self::MAX_COUNT, $daily));
        }

        if ($perReceiver < 1 || $perReceiver > self::MAX_COUNT) {
            throw InvalidKudos::because(\sprintf('The limit per receiver is 1 to %d thanks; it is %d.', self::MAX_COUNT, $perReceiver));
        }

        $start = new DateTimeImmutable('@0');
        $end = $start->add($period)->getTimestamp();

        if ($end < 3_600 || $end > 366 * 86_400) {
            throw InvalidKudos::because('The period of the limit per receiver lasts one hour to 366 days.');
        }
    }
}
