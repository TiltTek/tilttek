<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Export;

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Validation;

/**
 * An army list as plain text, to read, paste in a message or print: its name, its catalogue and points, each
 * detachment with its units, options and points, and the violations when a validation is given.
 */
final readonly class TextExport
{
    public function render(ArmyList $list, Catalogue $catalogue, ?Validation $validation = null): string
    {
        $sheet = Sheet::of($list, $catalogue, $validation);
        $lines = [$sheet->title, $sheet->subtitle];

        foreach ($sheet->detachments as $detachment) {
            $lines[] = '';
            $lines[] = $detachment['heading'];

            foreach ($detachment['units'] as $unit) {
                $lines[] = \sprintf('  - %s — %s', $unit['label'], $unit['points']);

                foreach ($unit['options'] as $option) {
                    $lines[] = '      '.$option;
                }
            }
        }

        if ([] !== $sheet->violations) {
            $lines[] = '';
            $lines[] = 'Violations';

            foreach ($sheet->violations as $violation) {
                $lines[] = '  - '.$violation;
            }
        }

        return implode("\n", $lines)."\n";
    }
}
