<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Buchholz', 'Sonneborn', 'Berger', 'pairer']],
    'deptrac' => [
        // The core is plain PHP on its requirements; `Seeding\RatingSeeding`, the adapter on tilttek/rating, which the
        // package suggests and requires in development only, alone sees it.
        'layers' => [
            'TilttekTournament' => '^Tilttek\\\\Tournament\\\\(?!Seeding\\\\RatingSeeding$).*',
            'TilttekTournamentRating' => '^Tilttek\\\\Tournament\\\\Seeding\\\\RatingSeeding$',
        ],
        'ruleset' => [
            'TilttekTournament' => ['DefaultAllowed'],
            'TilttekTournamentRating' => ['TilttekTournament', 'DefaultAllowed', 'TilttekRating'],
        ],
    ],
    // The pairing and the property tests replay whole tournaments: with coverage, the tests that cover the Swiss pairer
    // add up to more than Infection's 10 seconds, and Infection would skip those mutants rather than run them.
    'infection' => [
        'timeout' => 60,
        // Each exception changes the code and nothing it returns; the reason is above its pattern, which reads the
        // line of the code, not the mutant.
        'mutators' => [
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // For a size of 2 or more, `$size & ($size - 2)` is zero for the powers of 2 and only for them.
                    '.*\\$below = \\$size - 1;',
                    // 20 passes are far more than any round needs: 21 pair the same.
                    '.*private int \\$passes = 20,',
                    // The vertices are even, so 17 vertices never happen: a limit of 17 pairs as 16.
                    '.*private int \\$exactLimit = 16,',
                    // The exact search adds the same constant to every complete matching.
                    '.*\\$best = \\[\\$full => 0\\];',
                    // Only the upper half of the costs is read: the diagonal never is.
                    '.*\\$row\\[\\] = \\$i === \\$j \\? 0 : .*',
                    // A position in a score group is counted from any number: only the gaps between positions are read.
                    '.*\\$position = \\$seen\\[\\$points\\] \\?\\? 0;',
                    // Every complete pairing holds one bye edge, so a constant added to the cost of a bye changes none.
                    '.*\\$last = \\$count - 1;',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*private int \\$passes = 20,',
                    '.*\\$best = \\[\\$full => 0\\];',
                    '.*\\$row\\[\\] = \\$i === \\$j \\? 0 : .*',
                    '.*\\$position = \\$seen\\[\\$points\\] \\?\\? 0;',
                    '.*\\$last = \\$count - 1;',
                    // A participant out of the bracket ranks last at any depth under 1.
                    '.*\\$depth = static fn \\(string \\$participant\\): int => \\$reached\\[\\$participant\\] \\?\\? 0;',
                ],
            ],
            'Minus' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$last = \\$count - 1;',
                ],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    // Counting the positions down reads the same gaps: only their size is taken.
                    '.*\\$seen\\[\\$points\\] = \\$position \\+ 1;',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    // At `$p === $count` the inner loop has nothing left to exchange.
                    '.*for \\(\\$p = 0; \\$p < \\$count; \\+\\+\\$p\\) \\{',
                ],
            ],
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    // A sweep that exchanged nothing exchanges nothing the next time: only the time spent changes.
                    '.*\\$improved = false;',
                ],
            ],
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    // The participant of a slot of that kind is never null; the cast is for the return type.
                    '.*SlotKind::Participant => \\(string\\) \\$this->participant,',
                ],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    // The rounds are built in order, so their keys are already 0, 1, 2...
                    '.*return array_values\\(\\$rounds\\);',
                    // The pairer reads the order of the participants, not their keys.
                    '.*\\$active = array_values\\(array_diff\\(\\$participants, \\$this->state->withdrawn\\)\\);',
                ],
            ],
        ],
    ],
];
