<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;

/**
 * The standings a campaign uses: whole numbers from its lowest to its highest, where a faction starts with whom it has
 * met no one yet, and named tiers that cut it from the lowest up. It knows no game system: a campaign of three steps
 * either side and one of a hundred are both scales.
 */
final readonly class Scale
{
    /** The lowest end a scale may have. */
    public const int LOWEST = -100;

    /** The highest end a scale may have. */
    public const int HIGHEST = 100;

    /** The most tiers one scale holds. */
    public const int MAX_TIERS = 12;

    /**
     * @param int        $min   the lowest standing, `LOWEST` or more
     * @param int        $max   the highest standing, above `min` and `HIGHEST` or less
     * @param int        $start the standing of a subject no change has touched yet, from `min` to `max`
     * @param list<Tier> $tiers 1 to `MAX_TIERS`, from the lowest: the first starts at `min`, each next one higher, none
     *                          past `max`, each key once
     *
     * @throws InvalidRoster when a bound is out of its range, or the tiers do not cut the scale from its lowest up
     */
    public function __construct(public int $min, public int $max, public int $start, public array $tiers)
    {
        if ($min < self::LOWEST || $max > self::HIGHEST || $min >= $max) {
            throw InvalidRoster::because(\sprintf('A scale runs from %d or more to a higher end of %d or less.', self::LOWEST, self::HIGHEST));
        }

        if ($start < $min || $start > $max) {
            throw InvalidRoster::because('The starting standing of a scale lies within it.');
        }

        if ([] === $tiers || \count($tiers) > self::MAX_TIERS) {
            throw InvalidRoster::because(\sprintf('A scale holds 1 to %d tiers.', self::MAX_TIERS));
        }

        if ($tiers[0]->from !== $min) {
            throw InvalidRoster::because('The first tier of a scale starts at its lowest standing.');
        }

        $previous = null;
        foreach ($tiers as $tier) {
            if ($tier->from > $max || (null !== $previous && $tier->from <= $previous)) {
                throw InvalidRoster::because(\sprintf('The tier %s starts above the tier before it and within the scale.', $tier->key));
            }

            $previous = $tier->from;
        }

        $twice = Identifier::twice(array_map(static fn (Tier $tier): string => $tier->key, $tiers));

        if (null !== $twice) {
            throw InvalidRoster::because(\sprintf('The key %s comes twice among the tiers.', $twice));
        }
    }

    /**
     * Three steps either side of a neutral start: hostile at -3, unfriendly from -2, neutral at 0, friendly from 1 and
     * allied at 3.
     */
    public static function standard(): self
    {
        return new self(-3, 3, 0, [
            new Tier('hostile', 'Hostile', -3),
            new Tier('unfriendly', 'Unfriendly', -2),
            new Tier('neutral', 'Neutral', 0),
            new Tier('friendly', 'Friendly', 1),
            new Tier('allied', 'Allied', 3),
        ]);
    }

    public function contains(int $standing): bool
    {
        return $standing >= $this->min && $standing <= $this->max;
    }

    /**
     * The standing kept within the scale: a value past one end stops there.
     */
    public function clamp(int $standing): int
    {
        return max($this->min, min($this->max, $standing));
    }

    /**
     * The tier that holds the standing.
     *
     * @throws InvalidRoster when the standing is off the scale
     */
    public function tier(int $standing): Tier
    {
        if (!$this->contains($standing)) {
            throw InvalidRoster::because(\sprintf('The standing %d is off the scale, from %d to %d.', $standing, $this->min, $this->max));
        }

        // The first tier starts at the lowest standing, so the walk down from the highest always stops on a tier.
        for ($index = \count($this->tiers) - 1;; --$index) {
            if ($this->tiers[$index]->from <= $standing) {
                return $this->tiers[$index];
            }
        }
    }

    public function equals(self $other): bool
    {
        if ($this->min !== $other->min || $this->max !== $other->max || $this->start !== $other->start || \count($this->tiers) !== \count($other->tiers)) {
            return false;
        }

        return array_all($this->tiers, static fn (Tier $tier, int $index): bool => $tier->equals($other->tiers[$index]));
    }
}
