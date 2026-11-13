# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: schedules across changes of time,
29th of February and yearly rules, seasons and events and their limits, the questions of a moment, the reader and the
bridges. In the monorepo, Infection holds the package at 100 % of covered mutants.

`tests/Fuzz/seasons.php` is the fuzz target of the definitions, run by `composer fuzz`: any JSON text is refused with
the library's exception or gives seasons that, written and read back, are the same. `tests/Benchmark` measures a
snapshot and the announced events over a catalogue of a hundred seasons.

## Testing code that reads the seasons

Give `SeasonalEvents` an `InMemorySeasonCatalogue` and a `MockClock` set inside or outside a season, or pass the moment
to each call. Code that depends on `ActiveContent` takes a double of the port.
