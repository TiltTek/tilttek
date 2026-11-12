<?php

declare(strict_types=1);

namespace Tilttek\Weather;

/**
 * The days a forecast drew, in order, and the state the last one left, for the application to keep.
 */
final readonly class Forecast
{
    /**
     * @param non-empty-list<Day> $days
     */
    public function __construct(public array $days, public WeatherState $state)
    {
    }

    public function first(): Day
    {
        return $this->days[0];
    }

    public function last(): Day
    {
        return $this->days[\count($this->days) - 1];
    }
}
