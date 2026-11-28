<?php

declare(strict_types=1);

/*
 * The fuzz target of the hints read from the file name of a map image, run by `phil-dev fuzz`: any name gives hints
 * within their bounds, never an error, and the same hints every time.
 */

use Tilttek\Grid\MapHints;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $hints = MapHints::fromFileName($input);

    if (null !== $hints->pixelsPerCell && ($hints->pixelsPerCell < MapHints::MIN_PIXELS || $hints->pixelsPerCell > MapHints::MAX_PIXELS)) {
        throw new LogicException('Pixels per cell out of bounds.');
    }

    foreach ([$hints->columns, $hints->rows] as $cells) {
        if (null !== $cells && ($cells < 1 || $cells > MapHints::MAX_CELLS)) {
            throw new LogicException('Columns or rows out of bounds.');
        }
    }

    if ((null === $hints->columns) !== (null === $hints->rows)) {
        throw new LogicException('Columns without rows, or rows without columns.');
    }

    $again = MapHints::fromFileName($input);
    if ([$again->pixelsPerCell, $again->columns, $again->rows] !== [$hints->pixelsPerCell, $hints->columns, $hints->rows]) {
        throw new LogicException('The same name gives other hints.');
    }
});
$config->setMaxLen(300);
