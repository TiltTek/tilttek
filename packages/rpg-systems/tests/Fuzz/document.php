<?php

declare(strict_types=1);

/*
 * The fuzz target of the documents, run by `phil-dev fuzz`: any YAML text is either refused with the library's own
 * exceptions — not a document, not a system — or gives a system the writer writes and the reader reads back.
 */

use Tilttek\RpgSystems\Exception\RpgSystemsException;
use Tilttek\RpgSystems\Format\Syntax;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Format\SystemWriter;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $reader = new SystemReader(4096);

    try {
        $system = $reader->system($reader->decode("format: tilttek-rpg-systems/1\n".$input, Syntax::Yaml));
    } catch (RpgSystemsException) {
        // The refusals the reader promises for a text it cannot read.
        return;
    }

    $reader->system($reader->decode(new SystemWriter()->toJson($system), Syntax::Json));
});
$config->setMaxLen(1024);
