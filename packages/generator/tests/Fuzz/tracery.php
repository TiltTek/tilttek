<?php

declare(strict_types=1);

/*
 * The fuzz target of the Tracery importer, run by `phil-dev fuzz`: any text either is refused with the library's own
 * exceptions, or gives a document that the reader and the generator accept, whose first generator draws with a seed or
 * fails with the library's own exceptions.
 */

use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Import\TraceryImporter;
use Tilttek\Generator\Limits;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $imported = new TraceryImporter('fuzz.')->import($input);
    } catch (GeneratorException) {
        // The refusals the importer promises for a grammar it cannot read.
        return;
    }

    // A grammar the importer accepts makes a catalog the reader and the generator accept.
    $generator = new Generator($imported->catalog(), new Limits(picks: 200, milliseconds: 1000, results: 2, steps: 500, bytes: 4096, megabytes: 4));

    try {
        $generator->draw($imported->start, new Draw(2, seed: 'fuzz'));
    } catch (GeneratorException) {
        // A generator that calls itself, a limit: the library's own refusals.
    }
});
$config->setMaxLen(512);
