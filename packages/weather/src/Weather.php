<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Weather\Exception\InvalidState;
use Tilttek\Weather\Exception\UnknownClimate;

/**
 * The weather of a world, day after day, in one language: from the state the last day left, the next days and the
 * state they leave. Nothing is kept between forecasts; the same state and the same seed give the same days.
 *
 * ```php
 * $weather = new Weather(Climates::default(), 'fr');
 * $forecast = $weather->forecast(new WeatherState('temperate', 'autumn'), days: 3, seed: 'campaign-12');
 * $forecast->first()->label;   // "grand vent"
 * $forecast->state->toArray(); // what the application keeps for the next day
 * ```
 */
final readonly class Weather
{
    private Climates $climates;

    private string $locale;

    private Generator $generator;

    /**
     * @param ?Climates  $climates   the declared climates; those the library ships when null
     * @param string     $locale     the language of the labels
     * @param Randomizer $randomizer the randomness of forecasts without a seed
     *
     * @throws UnknownClimate when the labels are not written in this language
     */
    public function __construct(?Climates $climates = null, string $locale = 'en', Randomizer $randomizer = new Randomizer(new Secure()), Limits $limits = new Limits())
    {
        $this->climates = $climates ?? Climates::default();
        $this->locale = $this->climates->locale($locale);
        $this->generator = new Generator(WeatherTable::catalog($this->climates, $this->locale), $limits, randomizer: $randomizer);
    }

    public function climates(): Climates
    {
        return $this->climates;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * The next days from a state, and the state the last one leaves.
     *
     * @param int     $days how many days, within the limit of results of a draw
     * @param ?string $seed a seed that gives the same days for the same state
     *
     * @throws UnknownClimate     when the state names a zone, a season of the zone or a condition not declared
     * @throws InvalidState       when the state left by the draw cannot be read
     * @throws GeneratorException when the draw breaks a limit, or asks for less than one day
     */
    public function forecast(WeatherState $state, int $days = 1, ?string $seed = null): Forecast
    {
        $this->climates->climate($state->zone, $state->season);

        if (null !== $state->condition) {
            $this->climates->condition($state->condition);
        }

        $result = $this->generator->draw('weather', new Draw($days, seed: $seed, state: $state->toArray()));
        $list = [];

        foreach ($result->results as $day) {
            /** @var array{condition: string, label: string, temperature: string} $day */
            $list[] = new Day($day['condition'], $day['label'], (int) $day['temperature'], $state->zone, $state->season);
        }

        \assert([] !== $list);

        return new Forecast($list, WeatherState::fromArray($result->state ?? []));
    }
}
