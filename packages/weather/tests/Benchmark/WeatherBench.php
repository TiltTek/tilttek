<?php

declare(strict_types=1);

namespace Tilttek\Weather\Tests\Benchmark;

use Generator as Provider;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Generator\Limits;
use Tilttek\Weather\Climates;
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

/**
 * A campaign asks for the weather of a day, a week or a season: forecasts of 1, 7 and 90 days from the shipped
 * climates are measured, and the cost of building a `Weather`, which reads the climates and checks the generator.
 *
 * @BeforeMethods("setUp")
 */
final class WeatherBench
{
    private Weather $weather;

    private int $days = 1;

    public function __construct()
    {
        $this->weather = new Weather();
    }

    /**
     * @param array{days: int} $params
     */
    public function setUp(array $params): void
    {
        // The time limit is lifted: a loaded machine must not stop a measure.
        $this->weather = new Weather(Climates::default(), 'en', new Randomizer(new Xoshiro256StarStar(42)), new Limits(milliseconds: 60_000));
        $this->days = $params['days'];
    }

    /**
     * @ParamProviders("forecasts")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchForecast(): void
    {
        $this->weather->forecast(new WeatherState('temperate', 'autumn', 'fog', 9), $this->days);
    }

    /**
     * @ParamProviders("builds")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchBuild(): void
    {
        new Weather(Climates::default(), 'fr');
    }

    /**
     * @return Provider<string, array{days: int}>
     */
    public function forecasts(): Provider
    {
        yield '1 day' => ['days' => 1];
        yield '7 days' => ['days' => 7];
        yield '90 days' => ['days' => 90];
    }

    /**
     * @return Provider<string, array{days: int}>
     */
    public function builds(): Provider
    {
        yield 'shipped climates' => ['days' => 1];
    }
}
