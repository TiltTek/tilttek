<?php

declare(strict_types=1);

use Tilttek\Leaderboards\League\Leagues;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'docs' => ['public' => true, 'words' => ['HMAC', 'Predis', 'Valkey']],
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The weeks of a board still open, in `Leagues::settleBefore()`: they are listed from the earliest code, and
            // a week code sorts in time, so every week after one not over is not over either — stopping at the first
            // does what skipping it does.
            'Continue_' => [
                'ignore' => [
                    Leagues::class.'::settleBefore',
                ],
            ],
            // The quarter of a code like `2026-q4`, in `Schedule`: the regular expression has kept only digits, which
            // the multiplication converts on its own; the cast only serves PHPStan's rules.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'PeriodKind::Quarter => 1 === preg_match\\(.*3 \\* \\(int\\) \\$parts\\[\'quarter\'\\] - 2, 1\\) : null,',
                    // The microseconds of a moment, in `Ranking::key()`: `format('u')` gives digits, which the addition
                    // converts on its own; the cast only serves PHPStan's rules.
                    '.*\\+ \\(int\\) \\$score->reachedAt->format\\(\'u\'\\), \\$score->member->toString\\(\\)\\);',
                ],
            ],
            // The contributions put back in order, in `Measure::recount()`: they are sorted by the moment they occurred,
            // so none falls on an earlier day than the score's and `add()` never answers null; the fallback only
            // narrows the type.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$contribution, \\$timezone\\) \\?\\? \\[\\$value, \\$reachedAt\\];',
                ],
            ],
            // The cohort of a member without a score the week before, in `Leagues::enter()`: a score of 0 and a score of
            // less are both no score, and `cohortOf()` gives them the same cohort, 0.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*cohortOf\\(\\$this->scores->score\\(.*\\)->value \\?\\? 0\\).*',
                ],
            ],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : interfaces publiques hors d'un espace de noms Contract, à déplacer.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'paths' => [
                'src/LeaderboardObserver.php',
                'src/League/LeagueObserver.php',
                'src/Store/AppearanceStore.php',
                'src/Store/LeagueStore.php',
                'src/Store/ScoreStore.php',
                'src/Store/SnapshotStore.php',
            ]],
        ],
    ],
];
