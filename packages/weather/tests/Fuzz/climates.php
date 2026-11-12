<?php

declare(strict_types=1);

/*
 * The fuzz target of the climates, run by `phil-dev fuzz`: any YAML text either is refused with the library's own
 * `InvalidClimate`, or gives climates whose weather forecasts a day in every season of every zone, with a seed.
 */

use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Limits;
use Tilttek\Weather\ClimateReader;
use Tilttek\Weather\Exception\InvalidClimate;
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $climates = new ClimateReader()->read($input);
    } catch (InvalidClimate) {
        // The refusal the reader promises for a document it cannot read.
        return;
    }

    $weather = new Weather($climates, $climates->locales[0], limits: new Limits(milliseconds: 1000, results: 2));

    foreach ($climates->zones() as $zone) {
        foreach (array_keys($zone->seasons) as $season) {
            try {
                $weather->forecast(new WeatherState($zone->id, $season), 2, 'fuzz');
            } catch (GeneratorException) {
                // A limit of the generator: its own refusal.
            }
        }
    }
});
$config->setMaxLen(1024);
