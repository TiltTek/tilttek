# Installation

## Requirements

- PHP 8.5 or later, with `mbstring` and `sodium`.

## Installing

```sh
composer require tilttek/heraldry
```

## Wiring the library

Every service is a plain object without state between two calls, so one instance serves every request of a worker:

```php
use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Svg\Palette;
use Tilttek\Heraldry\Svg\SvgRenderer;
use Tilttek\Heraldry\TinctureRule;

$generator = new CoatGenerator(furs: true);        // whether a field may be of ermine or vair
$blazonry = new Blazonry();                        // the English and French writers, replaceable
$renderer = new SvgRenderer(new Palette(), $blazonry);
$rule = new TinctureRule();
```

In a Symfony application, register them as services; none needs a configuration.
