<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;

/**
 * The rolls, from one total to another inclusive, that end an activity with one of its outcomes.
 */
final readonly class Band
{
    /** The bounds of the totals a band covers, either way. */
    public const int MAX_TOTAL = 10_000;

    /**
     * @param int    $from    the lowest total, `-MAX_TOTAL` to `MAX_TOTAL`
     * @param int    $to      the highest total, `$from` to `MAX_TOTAL`
     * @param string $outcome the key of the outcome
     *
     * @throws InvalidDowntime when a total is out of its bounds, the band is empty, or the key is not one
     */
    public function __construct(public int $from, public int $to, public string $outcome)
    {
        if ($from < -self::MAX_TOTAL || $to > self::MAX_TOTAL || $from > $to) {
            throw InvalidDowntime::because(\sprintf('A band covers totals from -%1$d to %1$d, its lowest no higher than its highest.', self::MAX_TOTAL));
        }

        Identifier::key($outcome, 'an outcome');
    }
}
