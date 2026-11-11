<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use InvalidArgumentException;
use Tilttek\Dice\Expression\Validator;

/**
 * The faces a set of dice shows, without their order: how many dice show each value.
 */
final readonly class Faces
{
    /**
     * @var array<int, int> by value, in ascending order, each count at least 1
     */
    public array $counts;

    /**
     * @param array<int, int> $counts by value, in any order; the counts of zero are left out
     *
     * @throws InvalidArgumentException when a count is negative
     */
    public function __construct(array $counts)
    {
        foreach ($counts as $value => $count) {
            if ($count < 0) {
                throw new InvalidArgumentException(\sprintf('A value is shown by zero dice or more; %d dice show %d.', $count, $value));
            }
        }

        $counts = array_filter($counts, static fn (int $count): bool => $count > 0);
        ksort($counts);
        $this->counts = $counts;
    }

    /**
     * @param list<int> $values the value of each die
     */
    public static function of(array $values): self
    {
        return new self(array_count_values($values));
    }

    /**
     * How many dice the set holds.
     */
    public function dice(): int
    {
        return array_sum($this->counts);
    }

    /**
     * How many dice show this value.
     */
    public function showing(int $value): int
    {
        return $this->counts[$value] ?? 0;
    }

    /**
     * How many dice show a value that matches.
     */
    public function matching(Validator $validator): int
    {
        $dice = 0;

        foreach ($this->counts as $value => $count) {
            if ($validator->matches($value)) {
                $dice += $count;
            }
        }

        return $dice;
    }

    /**
     * The largest number of dice showing the same value.
     */
    public function mostOfAKind(): int
    {
        return [] === $this->counts ? 0 : max($this->counts);
    }

    /**
     * The length of the longest run of consecutive values shown.
     */
    public function longestRun(): int
    {
        $longest = 0;
        $start = null;
        $previous = null;

        foreach (array_keys($this->counts) as $value) {
            if (null === $previous || $value !== $previous + 1) {
                $start = $value;
            }

            $longest = max($longest, $value - $start + 1);
            $previous = $value;
        }

        return $longest;
    }
}
