<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;

/**
 * What an activity costs in one resource the system names — gold pieces, supplies, a favour —, once or for each day.
 * The library counts it; the application debits it.
 */
final readonly class Cost
{
    /** The largest amount of one cost. */
    public const int MAX_AMOUNT = 1_000_000;

    /**
     * @param string $resource the key of the resource
     * @param int    $amount   1 to `MAX_AMOUNT`
     *
     * @throws InvalidDowntime when the key is not one, or the amount is out of its bounds
     */
    public function __construct(
        public string $resource,
        public int $amount,
        public CostBasis $basis = CostBasis::Once,
    ) {
        Identifier::key($resource, 'a resource');

        if ($amount < 1 || $amount > self::MAX_AMOUNT) {
            throw InvalidDowntime::because(\sprintf('The amount of a cost is 1 to %d.', self::MAX_AMOUNT));
        }
    }

    public static function perDay(string $resource, int $amount): self
    {
        return new self($resource, $amount, CostBasis::PerDay);
    }

    /**
     * The amount for an activity of so many days.
     */
    public function total(int $days): int
    {
        return CostBasis::PerDay === $this->basis ? $this->amount * $days : $this->amount;
    }
}
