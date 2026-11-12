<?php

declare(strict_types=1);

namespace Tilttek\Weather;

/**
 * The weather of one day: its condition and the condition's label in the language of the forecast, its temperature in
 * degrees Celsius, and the zone and the season it fell in.
 */
final readonly class Day
{
    public function __construct(public string $condition, public string $label, public int $temperature, public string $zone, public string $season)
    {
    }

    /**
     * The temperature in degrees Fahrenheit, rounded to the nearest whole degree.
     */
    public function fahrenheit(): int
    {
        return (int) round($this->temperature * 9 / 5 + 32);
    }

    /**
     * @return array{condition: string, label: string, temperature: int, zone: string, season: string}
     */
    public function toArray(): array
    {
        return ['condition' => $this->condition, 'label' => $this->label, 'temperature' => $this->temperature, 'zone' => $this->zone, 'season' => $this->season];
    }
}
