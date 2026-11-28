<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use Tilttek\Inventory\Exception\InvalidInventory;

/**
 * The carrying rules a game system declares: what it counts, the capacity of a carrier when the application gives
 * none, its bands of load from the lightest, and whether a carrier may go beyond the last band.
 *
 * ```php
 * // Weight against a capacity, three bands, as a strength-based variant counts it
 * new CarryRules(Measure::Weight, 150, [
 *     new LoadBand('unencumbered', 1, 3),
 *     new LoadBand('encumbered', 2, 3),
 *     new LoadBand('heavily-encumbered', 1),
 * ]);
 *
 * // Slots, refused beyond them
 * new CarryRules(Measure::Slots, 10, [new LoadBand('fine', 1)], refuseOverCapacity: true);
 * ```
 *
 * The default capacity is 0 to 1,000,000,000,000; the bands are 1 to 10, under distinct keys, each ending above the one
 * before.
 */
final readonly class CarryRules
{
    public const int MAX_CAPACITY = 1_000_000_000_000;

    public const int MAX_BANDS = 10;

    /** @var non-empty-list<LoadBand> */
    public array $bands;

    /**
     * @param list<LoadBand> $bands from the lightest
     */
    public function __construct(
        public Measure $measure,
        public int $defaultCapacity,
        array $bands,
        public bool $refuseOverCapacity = false,
    ) {
        self::capacity($defaultCapacity);

        if ([] === $bands || \count($bands) > self::MAX_BANDS) {
            throw InvalidInventory::because(\sprintf('Carrying rules have 1 to %d load bands; these have %d.', self::MAX_BANDS, \count($bands)));
        }

        $keys = [];
        $previous = null;

        foreach ($bands as $band) {
            if (isset($keys[$band->key])) {
                throw InvalidInventory::because(\sprintf('The load band "%s" is declared twice.', $band->key));
            }

            if ($previous instanceof LoadBand && !$previous->endsBefore($band)) {
                throw InvalidInventory::because(\sprintf('The load band "%s" must end above "%s".', $band->key, $previous->key));
            }

            $keys[$band->key] = $band;
            $previous = $band;
        }

        $this->bands = $bands;
    }

    /**
     * Checks a carrier's capacity: 0 to 1,000,000,000,000.
     *
     * @throws InvalidInventory
     */
    public static function capacity(int $capacity): int
    {
        return Text::between($capacity, 0, self::MAX_CAPACITY, 'A carrying capacity');
    }

    /**
     * The lightest band a load fits in for a carrier of that capacity, or null beyond the last band.
     */
    public function bandFor(int $carried, int $capacity): ?LoadBand
    {
        foreach ($this->bands as $band) {
            if ($band->admits($carried, $capacity)) {
                return $band;
            }
        }

        return null;
    }

    /**
     * The last band: beyond it, a carrier is over its capacity.
     */
    public function lastBand(): LoadBand
    {
        return $this->bands[\count($this->bands) - 1];
    }
}
