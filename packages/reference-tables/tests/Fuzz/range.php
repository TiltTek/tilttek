<?php

declare(strict_types=1);

/*
 * The fuzz target of the ranges, run by `phil-dev fuzz`: any text is either refused with `InvalidTable`, or gives a
 * range whose printed form reads back as the same range.
 */

use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Range;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    foreach ([false, true] as $percentile) {
        try {
            $range = Range::parse($input, $percentile);
        } catch (InvalidTable) {
            continue;
        }

        $again = Range::parse($range->toString());

        if ($again->min !== $range->min || $again->max !== $range->max) {
            throw new LogicException(sprintf('The range "%s" reads back as "%s".', $range->toString(), $again->toString()));
        }
    }
});
$config->setMaxLen(40);
