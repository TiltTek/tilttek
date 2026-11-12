# Installation

## Requirements

- PHP 8.5 or later, with the `mbstring` extension, which measures the labels.
- `tilttek/generator`, whose tables with a memory draw the weather.
- `tilttek/dice`, which checks and rolls the daily change of the temperature.
- `symfony/yaml` 8.2, which reads the climates.

## Installing

```sh
composer require tilttek/weather
```

Nothing is registered and nothing is configured: build a `Weather` where you use it.

```php
use Tilttek\Weather\Climates;
use Tilttek\Weather\ClimateReader;
use Tilttek\Weather\Weather;

$shipped = new Weather(locale: 'en');
$ownWorld = new Weather(new ClimateReader()->file('config/climates.yaml'), 'fr');
```

Building a `Weather` reads the climates, writes the table with a memory and checks it as `tilttek/generator` checks any
catalog; build it once and keep it for the request or the process. It keeps nothing between forecasts, so one instance
serves every campaign of a long-running process.
