<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // The shipped data file is part of the package and returns a list of arrays, which the data tests read
            // row by row: the two refusals of a file of another form cannot be reached from it.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => [
                    '.*throw InvalidEntry::because\\(.\\?., .is a data file that does not return an array\\..\\).*',
                    '.*throw InvalidEntry::because\\(.\\?., .is a row of the data file that is not an array\\..\\).*',
                ],
            ],
        ],
    ],
];
