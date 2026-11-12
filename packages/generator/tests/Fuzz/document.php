<?php

declare(strict_types=1);

/*
 * The fuzz target of the documents, run by `phil-dev fuzz`: any YAML text either is refused — by Symfony Yaml, or with
 * the library's own exceptions when it is not a catalog or not a valid one — or gives a generator, every generator of
 * which draws a result with a seed, or fails with the library's own exceptions.
 */

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Loading\Reader;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $document = Yaml::parse($input);
        $generator = new Generator(new Reader()->catalog(['fuzz' => $document]), new Limits(picks: 200, milliseconds: 1000, results: 2, cells: 16));
    } catch (ParseException|GeneratorException) {
        // The refusals the reader and the generator promise for a document they cannot read.
        return;
    }

    foreach (array_keys($generator->catalog()->generators()) as $name) {
        try {
            $generator->draw($name, new Draw(2, seed: 'fuzz', state: ['a' => '1']));
        } catch (GeneratorException) {
            // A template that fails, a list that runs out, a limit: the library's own refusals.
        }
    }
});
$config->setMaxLen(512);
