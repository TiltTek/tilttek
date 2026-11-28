<?php

declare(strict_types=1);

/*
 * The fuzz target of the documents, run by `phil-dev fuzz`: any YAML text is either refused with the library's own
 * exceptions, or gives tables whose every row is found again by its key or by the minimum of its range.
 */

use Tilttek\ReferenceTables\Exception\ReferenceTablesException;
use Tilttek\ReferenceTables\Format\Syntax;
use Tilttek\ReferenceTables\Format\TableReader;
use Tilttek\ReferenceTables\Lookup;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $catalogue = new TableReader(4096)->parse("format: tilttek-reference-tables/1\nsource: {title: Fuzz, licence: owned}\n".$input, Syntax::Yaml);
    } catch (ReferenceTablesException) {
        // The refusals the reader promises for a text it cannot read.
        return;
    }

    foreach ($catalogue->tables() as $table) {
        foreach ($table->rows as $row) {
            $found = Lookup::Key === $table->lookup ? $table->row((string) $row->key) : $table->lookup((int) $row->range?->min);

            if ($found !== $row) {
                throw new LogicException(sprintf('The row "%s" of the table "%s" is not found again.', $row->label(), $table->key));
            }
        }
    }
});
$config->setMaxLen(1024);
