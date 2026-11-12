# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers the definitions and their bounds, the climates and their references, the reader and the place of each
problem, the transitions and temperatures written for `tilttek/generator`, the shipped generator files against the
shipped climates, and forecasts: the same seed gives the same days, each day follows from the day before through a
neighbour and by a few degrees, temperatures stay in the season's range, and a new season is reached day by day. In the
monorepo, Infection holds the library at 100 % of covered mutants.

## Benchmarks

`WeatherBench` measures forecasts of 1, 7 and 90 days from the shipped climates, about 0.2 ms a day, and the building of
a `Weather`, about 15 ms, which a process does once. From the monorepo root:

```sh
composer bench -- packages/tilttek-weather/tests/Benchmark
```

## Fuzzing

The fuzz target `climates` reads any YAML text: it is refused with an `InvalidClimate`, or gives climates whose weather
forecasts two days in every season of every zone. From the monorepo root, `composer fuzz -- climates`.

## Testing code that forecasts

Forecast with a seed, or give the `Weather` a seeded `Randomizer`, and compare with a second forecast:

```php
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

$weather = new Weather(randomizer: new Randomizer(new Xoshiro256StarStar(42)));
$state = new WeatherState('temperate', 'spring');
self::assertEquals($weather->forecast($state, 3, 'x'), $weather->forecast($state, 3, 'x'));
```

Climates of the application's own are checked when they are read: a test that reads the application's file fails on a
neighbour or a condition that is not declared.
