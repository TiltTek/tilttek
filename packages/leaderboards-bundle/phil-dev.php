<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['HMAC', 'Predis', 'Valkey']],
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // A membership read back after its insertion, in `DbalLeagueStore::join()`: the insertion writes the row or
            // finds the one there, so the reading always finds one; the exception only states what the type promises.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$this->membership\\(\\$board, \\$week, \\$member\\) \\?\\? throw new UnexpectedValueException.*',
                ],
            ],
            // The end of the week, in `LeaderboardsExtension::league()`: the schedule of a board with a league is weekly,
            // so a period always holds the moment; the null-safe call only states what the type allows.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$definition->schedule->at\\(\\$now\\)\\?->end,.*',
                ],
            ],
            // The lines asked of a snapshot, in `LeaderboardsExtension::celebration()`: `Leaderboards::frozen()` finds the
            // viewer's own line among all of them, whatever the limit; the limit only has to be a valid one.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->leaderboards->frozen\\(\\$board, \\$closed, \\$member, 1\\).*',
                    // The first scores read in `LeadWatch::save()`: only the first of them tells whether a member leads,
                    // and the second whether anyone else scored; reading one more changes neither.
                    '.*\\$this->scores->top\\(\\$score->board, \\$score->period, [12], \\$board->ranking\\).*',
                ],
            ],
            // The leader read before a score is kept, in `LeadWatch::save()`: every store reads at least one score.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->scores->top\\(\\$score->board, \\$score->period, 1, \\$board->ranking\\).*',
                ],
            ],
            // The weeks not settled, in `DbalLeagueStore::unsettled()`: every driver returns a string column as strings;
            // `Rows::text()` only narrows the type of each value.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    'return array_map\\(',
                ],
            ],
        ],
    ],
];
