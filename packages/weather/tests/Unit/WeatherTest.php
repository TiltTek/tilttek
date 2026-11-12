<?php

declare(strict_types=1);

namespace Tilttek\Weather\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Limits;
use Tilttek\Weather\ClimateReader;
use Tilttek\Weather\Climates;
use Tilttek\Weather\Day;
use Tilttek\Weather\Exception\InvalidState;
use Tilttek\Weather\Exception\UnknownClimate;
use Tilttek\Weather\Forecast;
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

#[CoversClass(Weather::class)]
#[CoversClass(WeatherState::class)]
#[CoversClass(Day::class)]
#[CoversClass(Forecast::class)]
#[CoversClass(InvalidState::class)]
final class WeatherTest extends TestCase
{
    public function testTheSameStateAndSeedGiveTheSameDays(): void
    {
        $weather = new Weather();
        $state = new WeatherState('temperate', 'autumn', 'fog', 9);

        $first = $weather->forecast($state, 10, 'campaign-12');
        $again = $weather->forecast($state, 10, 'campaign-12');

        self::assertEquals($first, $again);
        self::assertCount(10, $first->days);
        self::assertSame($first->days[0], $first->first());
        self::assertSame($first->days[9], $first->last());
        self::assertSame($first->last()->condition, $first->state->condition);
        self::assertSame($first->last()->temperature, $first->state->temperature);
        self::assertSame(['temperate', 'autumn'], [$first->state->zone, $first->state->season]);
        self::assertNotEquals($first, $weather->forecast($state, 10, 'campaign-13'));
    }

    public function testAForecastWithoutADaysCountIsOneDay(): void
    {
        $forecast = new Weather()->forecast(new WeatherState('temperate', 'autumn', 'fog', 9), seed: 'one');

        self::assertCount(1, $forecast->days);
    }

    public function testTheClimatesGivenAreTheOnesUsed(): void
    {
        $climates = new ClimateReader()->read(<<<'YAML'
            locales: [en]
            conditions:
              hail: { labels: { en: hail } }
            seasons:
              spring: { labels: { en: spring } }
            zones:
              moor:
                labels: { en: moor }
                seasons:
                  spring: { low: 0, high: 4, conditions: { hail: 1 } }
            YAML);
        $weather = new Weather($climates);

        self::assertSame($climates, $weather->climates());
        self::assertSame('hail', $weather->forecast(new WeatherState('moor', 'spring'), seed: 'x')->first()->condition);
    }

    public function testADayFollowsFromTheDayBefore(): void
    {
        $weather = new Weather();
        $climates = $weather->climates();

        foreach ($climates->zones() as $zone) {
            foreach ($zone->seasons as $season => $climate) {
                $state = new WeatherState($zone->id, $season);

                for ($step = 0; $step < 4; ++$step) {
                    $forecast = $weather->forecast($state, 15, $zone->id.$season.$step);
                    $before = $state;

                    foreach ($forecast->days as $day) {
                        self::assertArrayHasKey($day->condition, $climate->conditions);
                        self::assertGreaterThanOrEqual($climate->low, $day->temperature);
                        self::assertLessThanOrEqual($climate->high, $day->temperature);

                        if (null !== $before->condition && null !== $before->temperature) {
                            self::assertContains($day->condition, [$before->condition, ...$climates->neighbours($before->condition)], $before->condition.' → '.$day->condition);
                            self::assertLessThanOrEqual(4, abs($day->temperature - $before->temperature));
                        }

                        $before = new WeatherState($zone->id, $season, $day->condition, $day->temperature);
                    }

                    $state = $forecast->state;
                }
            }
        }
    }

    public function testTheWeatherMovesIntoANewSeasonDayByDay(): void
    {
        $weather = new Weather();
        $state = new WeatherState('temperate', 'summer', 'heatwave', 30);
        $forecast = $weather->forecast($state->withSeason('winter'), 14, 'turn');
        $temperatures = array_map(static fn (Day $day): int => $day->temperature, $forecast->days);

        self::assertSame([28, 26, 24, 22, 20, 18, 16, 14, 12, 10, 8, 6], \array_slice($temperatures, 0, 12));
        self::assertLessThanOrEqual(6, $temperatures[13]);
        self::assertSame('winter', $forecast->last()->season);
        self::assertNotSame('heatwave', $forecast->first()->condition);
        self::assertArrayHasKey($forecast->first()->condition, Climates::default()->climate('temperate', 'winter')->conditions);
    }

    public function testTheLabelsAreInTheLanguageOfTheWeather(): void
    {
        $state = new WeatherState('tropical', 'wet', 'downpour', 28);
        $english = new Weather(locale: 'en')->forecast($state, 3, 'labels');
        $french = new Weather(Climates::default(), 'fr')->forecast($state, 3, 'labels');

        self::assertSame(array_map(static fn (Day $day): string => $day->condition, $english->days), array_map(static fn (Day $day): string => $day->condition, $french->days));

        foreach ($french->days as $index => $day) {
            self::assertSame(Climates::default()->condition($day->condition)->labels['fr'], $day->label);
            self::assertSame(Climates::default()->condition($day->condition)->labels['en'], $english->days[$index]->label);
        }

        self::assertSame('fr', new Weather(locale: 'fr')->locale());
        self::assertSame('en', new Weather()->locale());

        $this->expectExceptionObject(UnknownClimate::of('language', 'de', ['en', 'fr']));

        new Weather(locale: 'de');
    }

    public function testAForecastWithoutASeedTakesTheRandomness(): void
    {
        $state = new WeatherState('arid', 'hot');
        $one = new Weather(randomizer: new Randomizer(new Xoshiro256StarStar(7)))->forecast($state, 5);
        $two = new Weather(randomizer: new Randomizer(new Xoshiro256StarStar(7)))->forecast($state, 5);

        self::assertEquals($one, $two);
    }

    public function testTheLimitsBoundAForecast(): void
    {
        $weather = new Weather(limits: new Limits(results: 3));

        self::assertCount(3, $weather->forecast(new WeatherState('arid', 'cool'), 3)->days);

        $this->expectException(LimitExceeded::class);

        $weather->forecast(new WeatherState('arid', 'cool'), 4);
    }

    public function testAStateNamesWhatTheClimatesDeclare(): void
    {
        $weather = new Weather();
        $states = [
            'The zone "polar" is not declared' => new WeatherState('polar', 'winter'),
            'The season of the zone "arid" "winter" is not declared' => new WeatherState('arid', 'winter'),
            'The condition "hail" is not declared' => new WeatherState('arid', 'hot', 'hail', 30),
        ];

        foreach ($states as $message => $state) {
            try {
                $weather->forecast($state);
                self::fail($message);
            } catch (UnknownClimate $unknown) {
                self::assertStringStartsWith($message, $unknown->getMessage());
            }
        }
    }

    public function testAStateIsKeptAsAMapOfTexts(): void
    {
        $state = new WeatherState('boreal', 'winter', 'snow', -21);

        self::assertSame(['zone' => 'boreal', 'season' => 'winter', 'condition' => 'snow', 'temperature' => '-21'], $state->toArray());
        self::assertEquals($state, WeatherState::fromArray($state->toArray()));
        self::assertEquals($state, WeatherState::fromArray(['zone' => 'boreal', 'season' => 'winter', 'condition' => 'snow', 'temperature' => -21]));
        self::assertSame(['zone' => 'boreal', 'season' => 'spring'], new WeatherState('boreal', 'spring')->toArray());
        self::assertEquals(new WeatherState('boreal', 'spring'), WeatherState::fromArray(['zone' => 'boreal', 'season' => 'spring']));
        self::assertEquals(new WeatherState('boreal', 'spring', 'snow', -21), $state->withSeason('spring'));
        self::assertEquals(new WeatherState('arid', 'cool', 'snow', -21), $state->withZone('arid', 'cool'));
        self::assertSame(200, new WeatherState('a', 'b', null, 200)->temperature);
        self::assertSame(-200, new WeatherState('a', 'b', null, -200)->temperature);
        self::assertSame(200, WeatherState::TEMPERATURE);
        self::assertSame(-200, WeatherState::fromArray(['zone' => 'a', 'season' => 'b', 'temperature' => '-200'])->temperature);
    }

    public function testAnUnreadableStateIsAnError(): void
    {
        $states = [
            'names its zone and its season, and its condition as a text' => [['season' => 'b'], ['zone' => 'a'], ['zone' => 1, 'season' => 'b'], ['zone' => 'a', 'season' => 'b', 'condition' => 3]],
            'holds its temperature as a whole number of degrees' => [['zone' => 'a', 'season' => 'b', 'temperature' => '1.5'], ['zone' => 'a', 'season' => 'b', 'temperature' => 'warm'], ['zone' => 'a', 'season' => 'b', 'temperature' => '1000'], ['zone' => 'a', 'season' => 'b', 'temperature' => ['1']], ['zone' => 'a', 'season' => 'b', 'temperature' => "1\n"], ['zone' => 'a', 'season' => 'b', 'temperature' => 'x1']],
        ];

        foreach ($states as $problem => $arrays) {
            foreach ($arrays as $array) {
                try {
                    WeatherState::fromArray($array);
                    self::fail(json_encode($array, \JSON_THROW_ON_ERROR));
                } catch (InvalidState $invalid) {
                    self::assertSame('The state of the weather '.$problem.'.', $invalid->getMessage());
                }
            }
        }

        foreach ([201, -201] as $temperature) {
            try {
                new WeatherState('a', 'b', null, $temperature);
                self::fail((string) $temperature);
            } catch (InvalidState $invalid) {
                self::assertSame('The state of the weather holds a temperature from -200 to 200 degrees.', $invalid->getMessage());
            }
        }
    }

    public function testADayGivesItsTemperatureInFahrenheit(): void
    {
        $day = new Day('clear', 'clear skies', 21, 'temperate', 'summer');

        self::assertSame(70, $day->fahrenheit());
        self::assertSame(-40, new Day('snow', 'snow', -40, 'boreal', 'winter')->fahrenheit());
        self::assertSame(32, new Day('fog', 'fog', 0, 'temperate', 'autumn')->fahrenheit());
        self::assertSame(36, new Day('fog', 'fog', 2, 'temperate', 'autumn')->fahrenheit());
        self::assertSame(37, new Day('fog', 'fog', 3, 'temperate', 'autumn')->fahrenheit());
        self::assertSame(['condition' => 'clear', 'label' => 'clear skies', 'temperature' => 21, 'zone' => 'temperate', 'season' => 'summer'], $day->toArray());
    }
}
