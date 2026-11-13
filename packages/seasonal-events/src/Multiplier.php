<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use Tilttek\SeasonalEvents\Exception\InvalidSeason;

/**
 * A multiplier of the gains of a ledger while an announced event lasts, in percent: 150 adds half, 200 doubles. The
 * application applies it where it credits the ledger; multipliers of events that meet never add up — the highest
 * applies.
 */
final readonly class Multiplier
{
    public const int MIN_PERCENT = 101;

    public const int MAX_PERCENT = 500;

    public function __construct(
        public string $ledger,
        public int $percent,
    ) {
        if (1 !== preg_match(Season::IDENTIFIER, $ledger)) {
            throw InvalidSeason::because(\sprintf('A ledger is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $ledger));
        }

        if ($percent < self::MIN_PERCENT || $percent > self::MAX_PERCENT) {
            throw InvalidSeason::because(\sprintf('A multiplier is %d to %d percent; %d is not.', self::MIN_PERCENT, self::MAX_PERCENT, $percent));
        }
    }

    /**
     * An amount multiplied, rounded down: a gain never grows by a fraction of a point.
     */
    public function apply(int $amount): int
    {
        return intdiv($amount * $this->percent, 100);
    }

    /**
     * @return array{ledger: string, percent: int}
     */
    public function toArray(): array
    {
        return ['ledger' => $this->ledger, 'percent' => $this->percent];
    }
}
