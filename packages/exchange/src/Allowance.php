<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Points\Rule\Period;

/**
 * How much a member may give over a period — points for transfers, offers for trades —: *500 points a week*,
 * *10 offers a day*. The periods are those of the earning caps of `tilttek/points`, counted in the guards' timezone.
 */
final readonly class Allowance
{
    public function __construct(
        public int $limit,
        public Period $period,
    ) {
        if ($limit < 1) {
            throw InvalidExchange::because(\sprintf('An allowance allows at least 1; %d is not an allowance.', $limit));
        }
    }

    /**
     * The start, included, and the end, excluded, of the period holding the moment; both null for `Period::Ever`.
     *
     * @return array{?DateTimeImmutable, ?DateTimeImmutable}
     */
    public function window(DateTimeImmutable $moment, DateTimeZone $timezone): array
    {
        return $this->period->bounds($moment, $timezone);
    }
}
