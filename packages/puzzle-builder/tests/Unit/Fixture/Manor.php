<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit\Fixture;

use Tilttek\PuzzleBuilder\Scenario;

/**
 * A scenario of four puzzles: the clock and the painting open from the start, the letter is handed out by the host,
 * and the vault opens once the clock and the painting are solved and ends the scenario.
 */
final class Manor
{
    private function __construct()
    {
    }

    public static function scenario(): Scenario
    {
        return Scenario::fromArray([
            'id' => 'manor',
            'finale' => 'vault',
            'puzzles' => [
                [
                    'id' => 'clock',
                    'title' => 'The clock',
                    'answers' => ['Midnight', 'Minuit'],
                    'partials' => ['twelve' => 'Which twelve?'],
                    'hints' => [['text' => 'Look at the hands', 'penalty' => 60], ['text' => 'Both point up']],
                ],
                ['id' => 'painting', 'title' => 'The painting', 'answers' => ['Le Chêne']],
                ['id' => 'letter', 'title' => 'A letter', 'answers' => ['Rosebud'], 'unlock' => ['manual' => true]],
                ['id' => 'vault', 'title' => 'The vault', 'answers' => ['4521'], 'hints' => [['text' => 'Count the portraits']], 'unlock' => ['requires' => ['clock', 'painting']]],
            ],
        ]);
    }
}
