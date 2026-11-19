<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'advisory'],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // `isset()` is true for a key holding false as well: the flag is read by its key alone.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$this->opened\\[\\$event->idempotencyKey\\(\\)\\] = true;']],
            // `sort()` renumbers the list read next, so the gaps `array_unique()` leaves are never seen.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*\\$this->calendars\\[\\$member\\] = array_values\\(array_unique\\(.*']],
        ],
    ],
];
