# tilttek/weather

**Weather in the game world, day after day, following from the day before.**

tilttek/weather gives the weather of a campaign's world: from the state the last day left — the climate zone, the
season, the condition of the sky and the temperature — it draws the next days and the state they leave. A day follows
from the one before: a condition stays, as often as the zone's persistence says, or turns into one of its neighbours
that the season brings — fair weather clouds over, an overcast sky turns to drizzle, then to rain — never from clear
skies to a blizzard overnight; the temperature moves by a few degrees a day toward what the season and the condition
call for, and stays in the season's range. When the application moves the calendar to the next season, the weather
moves into it day by day. The seasons, the climate zones and the conditions are declared data, in YAML: five zones
ship — temperate, boreal, mediterranean, arid and tropical — with sixteen conditions labelled in English and in French,
and an application adds its own worlds. The same state and the same seed give the same days.

It is for any PHP code that needs a world's weather: a game master's toolbox, a campaign journal, a journey or a
hexcrawl that asks what the sky does each day, a virtual table. It keeps nothing: the application stores the state —
by campaign, by table — and passes it back, or lets `tilttek/generator-bundle` keep it.

It is a PHP library on `tilttek/generator`: the weather is a table with a memory of that library, written from the
declared climates, so that it draws with the generator's seeds, limits and sandbox, and loads as a generator file in
an application that keeps its generators in files. The temperature's daily change is a roll of `tilttek/dice`, checked
when the climates are read. `symfony/yaml` reads the climates.

The design is small: a `ClimateReader` reads `Climates` — `Condition`, `Season` and `Zone` with what each
`SeasonalClimate` brings —, `WeatherTable` writes them as a catalog or a YAML file of `tilttek/generator`, and `Weather`
draws a `Forecast` of `Day`s from a `WeatherState`.

## Status

| Part | State |
|---|---|
| Declared climates: conditions with their labels, temperature and neighbours, seasons, zones with their seasonal ranges, weights, persistence and daily roll; every reference checked | Implemented and tested |
| Climates read from YAML, every problem with its place; the shipped climates in English and French | Implemented and tested |
| The weather as a table with a memory of `tilttek/generator`: transitions through neighbours, persistence, temperatures within the season, a new season reached day by day | Implemented and tested |
| `Weather`: forecasts from a state, labels in a language, reproducible from a seed, within the generator's limits | Implemented and tested |
| Generator files in English and French, for an application that loads its generators from files | Implemented and tested |
| Exceptions under one `WeatherException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Forecasting](doc/forecasting.md) | `Weather`, `WeatherState`, `Forecast` and `Day`; seeds, seasons, journeys; keeping the state with `tilttek/generator-bundle`. |
| [Climates](doc/climates.md) | The YAML document of the climates, the shipped zones, and how a day follows from the day before. |
| [Extension points](doc/extension-points.md) | The climates, the randomness and the limits an application gives. |
| [Exceptions](doc/exceptions.md) | `WeatherException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, and how to test code that forecasts. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/weather
```

```php
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

$weather = new Weather(locale: 'fr');

$today = $weather->forecast(new WeatherState('temperate', 'autumn'), seed: 'campaign-12 day-1');
$today->first()->label;          // "gelée blanche"
$today->first()->temperature;    // 6

$week = $weather->forecast($today->state, days: 7, seed: 'campaign-12 day-2');
$store->save($week->state->toArray());   // the application keeps the state for the next day
```

## Requirements

PHP 8.5 or later, with the `mbstring` extension.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
