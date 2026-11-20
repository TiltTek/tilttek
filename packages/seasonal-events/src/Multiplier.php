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
     * An amount multiplied, rounded down: a gain never grows by a fraction of a point. The hundreds and the rest are
     * multiplied apart, so that only a result an integer cannot hold is refused.
     *
     * @throws InvalidSeason when the amount multiplied is more than an integer holds
     */
    public function apply(int $amount): int
    {
        $hundreds = intdiv($amount, 100);
        $rest = intdiv($amount % 100 * $this->percent, 100);

        // The hundreds and the rest have the sign of the amount: past these bounds, their sum would not fit.
        $highest = intdiv(\PHP_INT_MAX - max($rest, 0), $this->percent);
        $lowest = intdiv(\PHP_INT_MIN - min($rest, 0), $this->percent);

        if ($hundreds > $highest || $hundreds < $lowest) {
            throw InvalidSeason::because(\sprintf('%d multiplied by %d percent is more than an integer holds.', $amount, $this->percent));
        }

        return $hundreds * $this->percent + $rest;
    }

    /**
     * @return array{ledger: string, percent: int}
     */
    public function toArray(): array
    {
        return ['ledger' => $this->ledger, 'percent' => $this->percent];
    }
}
