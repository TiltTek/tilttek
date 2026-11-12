<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Tilttek\Weather\Exception\InvalidClimate;

/**
 * What one season brings to one zone: the range of its temperatures, in degrees Celsius, and how often each condition
 * comes, as whole weights.
 */
final readonly class SeasonalClimate
{
    /**
     * The coldest and the hottest temperature a range may reach, in degrees Celsius.
     */
    public const int TEMPERATURE = 80;

    /**
     * The heaviest weight of a condition.
     */
    public const int WEIGHT = 1000;

    /**
     * @var array<string, int> by condition
     */
    public array $conditions;

    /**
     * @param array<array-key, mixed> $conditions weights, by condition
     *
     * @throws InvalidClimate
     */
    public function __construct(public string $season, public int $low, public int $high, array $conditions, string $place = 'season')
    {
        Rules::between($place.'.low', $low, -self::TEMPERATURE, self::TEMPERATURE);
        Rules::between($place.'.high', $high, $low, self::TEMPERATURE);

        if ([] === $conditions) {
            throw InvalidClimate::at($place.'.conditions', 'a season brings at least one condition');
        }

        $weights = [];

        foreach ($conditions as $condition => $weight) {
            $condition = Rules::identifier($place.'.conditions', (string) $condition);

            if (!\is_int($weight)) {
                throw InvalidClimate::at($place.'.conditions.'.$condition, \sprintf('a weight is a whole number from 1 to %d', self::WEIGHT));
            }

            $weights[$condition] = Rules::between($place.'.conditions.'.$condition, $weight, 1, self::WEIGHT);
        }

        $this->conditions = $weights;
    }

    /**
     * The middle of the range, rounded down.
     */
    public function middle(): int
    {
        return (int) floor(($this->low + $this->high) / 2);
    }
}
