<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use Tilttek\Bounties\Exception\InvalidBounty;

/**
 * The points a bounty pays, paid in advance by its sponsor: an amount of a unit of points of the site, `points` or `gems` — a currency
 * code of `tilttek/points`. A prize is never money, and nothing in the library converts it to money.
 */
final readonly class Prize
{
    public const int MAX_AMOUNT = 1_000_000;

    public function __construct(
        public int $amount,
        public string $unit = 'points',
    ) {
        if ($amount < 1 || $amount > self::MAX_AMOUNT) {
            throw InvalidBounty::because(\sprintf('A prize is 1 to %d points; %d is not.', self::MAX_AMOUNT, $amount));
        }

        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $unit)) {
            throw InvalidBounty::because(\sprintf('"%s" is not a unit of points: 1 to 32 lower-case letters, digits or "_", starting with a letter.', $unit));
        }
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->unit === $other->unit;
    }
}
