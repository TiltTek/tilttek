<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Exception\InvalidEntry;

/**
 * A range of whole numbers, both ends included: the numbers of players, the minutes of a game, the levels of a
 * scenario. A single value is a range whose ends are equal.
 */
final readonly class Range
{
    public const int MAX = 100_000;

    /**
     * @throws InvalidEntry when an end is under 0 or over 100 000, or the minimum is over the maximum
     */
    public function __construct(public int $min, public int $max)
    {
        if ($min < 0 || $max > self::MAX || $min > $max) {
            throw InvalidEntry::because(\sprintf('A range is from 0 to %d, its minimum at most its maximum.', self::MAX));
        }
    }

    public static function exactly(int $value): self
    {
        return new self($value, $value);
    }

    public function contains(int $value): bool
    {
        return $value >= $this->min && $value <= $this->max;
    }

    public function equals(self $other): bool
    {
        return $this->min === $other->min && $this->max === $other->max;
    }

    /**
     * @throws InvalidEntry when the range is not within the bounds of the field
     */
    public function within(int $floor, int $ceiling, string $what): self
    {
        if ($this->min < $floor || $this->max > $ceiling) {
            throw InvalidEntry::because(\sprintf('The %s are from %d to %d.', $what, $floor, $ceiling));
        }

        return $this;
    }
}
