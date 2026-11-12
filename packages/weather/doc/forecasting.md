# Forecasting

## A day, then the next

```php
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

$weather = new Weather(locale: 'en');
$forecast = $weather->forecast(new WeatherState('boreal', 'winter'), days: 3, seed: 'north-road 1');

foreach ($forecast->days as $day) {
    echo $day->label, ', ', $day->temperature, ' °C (', $day->fahrenheit(), ' °F)', "\n";
}
// hard frost, -24 °C (-11 °F)
// hard frost, -25 °C (-13 °F)
// hard frost, -26 °C (-15 °F)

$forecast->state->toArray();
// ['zone' => 'boreal', 'season' => 'winter', 'condition' => 'frost', 'temperature' => '-26']
```

`Weather::forecast($state, $days, $seed)` draws the next days from a state and returns a `Forecast`: its `days`, in
order — `first()` and `last()` — and the `state` the last day leaves. A `WeatherState` holds the zone and the season,
which the application sets, and the condition and the temperature the last day left; a state without a condition — the
first day of a campaign — starts from a condition drawn by the season's weights and from the middle of the season's
range moved by that condition.

A `Day` holds its `condition`, its `label` in the language of the `Weather`, its `temperature` in degrees Celsius, its
`zone` and its `season`; `fahrenheit()` gives the temperature in degrees Fahrenheit and `toArray()` all of it. The
labels of the zone and the season come from the climates: `$weather->climates()->season($day->season)->labels['fr']`.

The number of days is bounded by the limit of results of `tilttek/generator` — 100 by default, `new Weather(limits: new
Limits(results: 366))` for a year — and a forecast of fewer than one day is an `InvalidDefinition` of that library.

## Seeds

The same state and the same seed give the same days, in any process: a seed made of the campaign and the date —
`'campaign-12 1472-03-04'` — makes the weather of a day the same each time it is asked. Without a seed, the
`Randomizer` given to the `Weather` decides, a secure one by default.

## Keeping the state

The library keeps nothing. The application stores `$forecast->state->toArray()`, a map of short texts, and reads it
back with `WeatherState::fromArray()`, which refuses a state without its zone or season or with a temperature that is
not a whole number of degrees (`InvalidState`). A state that names a zone, a season of the zone or a condition the
climates do not declare is an `UnknownClimate` when it is forecast.

## Seasons and journeys

The calendar of the game is the application's: when it turns to the next season, the application changes the season of
the state and keeps the rest.

```php
$spring = $weather->forecast($winter->state->withSeason('spring'), days: 5);
```

The weather moves into the new season day by day: a condition the new season does not bring turns into one of its
neighbours that it brings, and a temperature out of the new range comes back by two degrees a day. A journey into
another climate changes the zone in the same way, `withZone('arid', 'hot')`.

## With `tilttek/generator-bundle`

The weather is a table with a memory of `tilttek/generator`; the library ships it as generator files, written from its
own climates — `WeatherTable::directory('en')` and `WeatherTable::directory('fr')` hold a `weather.yaml` each. An
application that keeps its generators in files adds one of them to the paths of `tilttek/generator-bundle`, whose
`MemoryTables` then keeps the state by key:

```yaml
tilttek_generator:
    paths:
        - '%kernel.project_dir%/config/generators'
        - '%kernel.project_dir%/vendor/tilttek/weather/generators/fr'
```

```php
$tables->set('weather', 'campaign-12', ['zone' => 'temperate', 'season' => 'autumn']);
$tables->draw('weather', 'campaign-12', count: 3, seed: 'campaign-12')->results;
// [['condition' => 'wind', 'label' => 'grand vent', 'temperature' => '8'], …]
```

The results are arrays of texts — `condition`, `label` and `temperature` — and the state is that of `WeatherState`.
An application with climates of its own writes its file with `WeatherTable::yaml($climates, 'fr')`, or builds a
catalog with `WeatherTable::catalog($climates, 'fr', 'weather.north')` under another name.
