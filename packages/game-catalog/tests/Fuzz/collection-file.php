<?php

declare(strict_types=1);

/*
 * Collection files nobody wrote: whatever the file, the reader returns its rows within the limits, or refuses it with
 * an exception of the library; every row it returns holds an identifier of its database, statuses without repeats, a
 * priority of 1 to 5 and plays within their bounds.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Tilttek\GameCatalog\CollectionStatus;
use Tilttek\GameCatalog\Exception\CatalogException;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Import\CollectionReader;
use Tilttek\GameCatalog\Import\Columns;
use Tilttek\GameCatalog\Import\Limits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new CollectionReader();
$limits = new Limits(bytes: 4_096, rows: 50, columns: 20, cellBytes: 64, errors: 10);
$columnSets = [Columns::geekExport(IdentifierScheme::RpgGeek), new Columns('id', schemeColumn: 'db', delimiter: ';')];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $limits, $columnSets): void {
    foreach ($columnSets as $columns) {
        try {
            $file = $reader->read($input, $columns, $limits);
        } catch (CatalogException) {
            // The refusal the reader promises for a file it cannot read as a whole.
            continue;
        }

        if (count($file->rows) > $limits->rows || count($file->errors) > $limits->errors) {
            throw new LogicException('The reader returned more rows or errors than its limits.');
        }

        foreach ($file->rows as $row) {
            if (!$row->identifier->scheme->accepts($row->identifier->value)
                || count(array_unique(array_map(static fn (CollectionStatus $status): string => $status->value, $row->statuses))) !== count($row->statuses)
                || (null !== $row->priority && ($row->priority < 1 || $row->priority > 5))
                || (null !== $row->plays && ($row->plays < 0 || $row->plays > 100_000))) {
                throw new LogicException('The reader returned a row out of its shape.');
            }
        }
    }
});
$config->setMaxLen(4_096);
