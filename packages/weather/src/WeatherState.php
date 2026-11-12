<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Tilttek\Weather\Exception\InvalidState;

/**
 * Where the weather is: the climate zone and the season the application sets, and the condition and the temperature,
 * in degrees Celsius, that the last day left — none before the first day. The library keeps no state: the application
 * stores it, as the map of texts `toArray()` gives, and passes it back.
 */
final readonly class WeatherState
{
    /**
     * The coldest and hottest temperature a state holds.
     */
    public const int TEMPERATURE = 200;

    /**
     * @throws InvalidState when the temperature is out of bounds
     */
    public function __construct(public string $zone, public string $season, public ?string $condition = null, public ?int $temperature = null)
    {
        if (null !== $temperature && abs($temperature) > self::TEMPERATURE) {
            throw InvalidState::of(\sprintf('holds a temperature from -%1$d to %1$d degrees', self::TEMPERATURE));
        }
    }

    /**
     * The state a table with a memory left, or one the application kept.
     *
     * @param array<array-key, mixed> $state
     *
     * @throws InvalidState
     */
    public static function fromArray(array $state): self
    {
        $zone = $state['zone'] ?? null;
        $season = $state['season'] ?? null;
        $condition = $state['condition'] ?? null;
        $temperature = $state['temperature'] ?? null;

        if (!\is_string($zone) || !\is_string($season) || (null !== $condition && !\is_string($condition))) {
            throw InvalidState::of('names its zone and its season, and its condition as a text');
        }

        if (null !== $temperature && (!\is_scalar($temperature) || 1 !== preg_match('/^-?\d{1,3}$/D', (string) $temperature))) {
            throw InvalidState::of('holds its temperature as a whole number of degrees');
        }

        return new self($zone, $season, $condition, null === $temperature ? null : (int) $temperature);
    }

    /**
     * The state as the table with a memory reads it: the zone and the season, then the condition and the temperature
     * when a day has left them.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $state = ['zone' => $this->zone, 'season' => $this->season];

        if (null !== $this->condition) {
            $state['condition'] = $this->condition;
        }

        if (null !== $this->temperature) {
            $state['temperature'] = (string) $this->temperature;
        }

        return $state;
    }

    /**
     * The same weather in another season: the condition and the temperature move into the new season day by day.
     */
    public function withSeason(string $season): self
    {
        return new self($this->zone, $season, $this->condition, $this->temperature);
    }

    /**
     * The same weather in another zone and season — a journey that crosses into another climate.
     */
    public function withZone(string $zone, string $season): self
    {
        return new self($zone, $season, $this->condition, $this->temperature);
    }
}
