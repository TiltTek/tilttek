<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit\Fixture;

use Tilttek\EscapeRoom\Room;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Hint;
use Tilttek\PuzzleBuilder\Puzzle;

/**
 * A room of three chained puzzles — the candle, the crest, the tomb —, an hour long, a hint costing 2 minutes unless
 * its own penalty says otherwise, a skip 5 minutes, for 2 to 6 players.
 */
final class Crypt
{
    private function __construct()
    {
    }

    public static function room(): Room
    {
        return Room::chain('crypt', 'The crypt', [
            new Puzzle('candle', 'The candle', new AnswerKey(['wick']), [new Hint('Look at the top'), new Hint('It burns', 30)]),
            new Puzzle('crest', 'The crest', new AnswerKey(['lion'])),
            new Puzzle('tomb', 'The tomb', new AnswerKey(['1347']), [new Hint('A year')]),
        ], hintPenalty: 120, skipPenalty: 300);
    }
}
