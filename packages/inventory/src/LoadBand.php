<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

/**
 * A band of load a system declares, under a key — `unencumbered`, `encumbered`, `heavily-encumbered` —, up to a share
 * of the carrier's capacity written as a fraction: 1/3, 2/3, 1/1. Numerator and denominator are 1 to 1000; the
 * fraction may pass 1 for a band beyond the capacity a system still allows.
 */
final readonly class LoadBand
{
    public const int MAX_TERM = 1000;

    public function __construct(
        public string $key,
        public int $numerator,
        public int $denominator = 1,
    ) {
        Text::key($key, 'a load band');
        Text::between($numerator, 1, self::MAX_TERM, 'The numerator of a load band');
        Text::between($denominator, 1, self::MAX_TERM, 'The denominator of a load band');
    }

    /**
     * Whether a load fits in the band for a carrier of that capacity, in whole numbers.
     */
    public function admits(int $carried, int $capacity): bool
    {
        return $carried * $this->denominator <= $capacity * $this->numerator;
    }

    /**
     * Whether this band ends below the other.
     */
    public function endsBefore(self $other): bool
    {
        return $this->numerator * $other->denominator < $other->numerator * $this->denominator;
    }

    /**
     * The most a carrier of that capacity carries in the band, rounded down.
     */
    public function limitFor(int $capacity): int
    {
        return intdiv($capacity * $this->numerator, $this->denominator);
    }
}
