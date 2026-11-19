<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Fixture;

/**
 * The rooms of the tests, as the configuration gives them: the crypt — three chained puzzles, the candle, the crest and
 * the tomb, an hour long, a hint costing 2 minutes unless its own penalty says otherwise, a skip 5 minutes, for 2 to 6
 * players — and the cell, of one puzzle, a minute long.
 */
final class Rooms
{
    private function __construct()
    {
    }

    /**
     * @return array<string, array{title: string, duration: int, hint_penalty: int, skip_penalty: int, min_players: int, max_players: int, scenario: array<array-key, mixed>}>
     */
    public static function config(): array
    {
        return [
            'crypt' => [
                'title' => 'The crypt',
                'duration' => 3_600,
                'hint_penalty' => 120,
                'skip_penalty' => 300,
                'min_players' => 2,
                'max_players' => 6,
                'scenario' => [
                    'id' => 'crypt',
                    'finale' => 'tomb',
                    'puzzles' => [
                        ['id' => 'candle', 'title' => 'The candle', 'answers' => ['wick'], 'hints' => [['text' => 'Look at the top'], ['text' => 'It burns', 'penalty' => 30]]],
                        ['id' => 'crest', 'title' => 'The crest', 'answers' => ['lion'], 'unlock' => ['requires' => ['candle']]],
                        ['id' => 'tomb', 'title' => 'The tomb', 'answers' => ['1347'], 'hints' => [['text' => 'A year']], 'unlock' => ['requires' => ['crest']]],
                    ],
                ],
            ],
            'cell' => [
                'title' => 'The cell',
                'duration' => 60,
                'hint_penalty' => 0,
                'skip_penalty' => 0,
                'min_players' => 1,
                'max_players' => 4,
                'scenario' => ['id' => 'cell', 'finale' => 'door', 'puzzles' => [['id' => 'door', 'title' => 'The door', 'answers' => ['key']]]],
            ],
        ];
    }
}
