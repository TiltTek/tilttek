<?php

declare(strict_types=1);

/*
 * The fuzz target of the Perchance importer, run by `phil-dev fuzz`: any text either is refused with the library's own
 * exceptions, or gives a document that the reader and the generator accept, whose first generator draws with a seed or
 * fails with the library's own exceptions.
 */

use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Import\PerchanceImporter;
use Tilttek\Generator\Limits;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $imported = new PerchanceImporter('fuzz.')->import($input);
    } catch (GeneratorException) {
        // The refusals the importer promises for tables it cannot read.
        return;
    }

    // Tables the importer accepts make a catalog the reader and the generator accept.
    $generator = new Generator($imported->catalog(), new Limits(picks: 200, milliseconds: 1000, results: 2, steps: 500, bytes: 4096, megabytes: 4));

    try {
        $generator->draw($imported->start, new Draw(2, seed: 'fuzz'));
    } catch (GeneratorException) {
        // A section that draws itself, a limit: the library's own refusals.
    }
});
$config->setMaxLen(512);
