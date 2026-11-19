<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

use Tilttek\PuzzleBuilder\HintResult;

/**
 * The hint revealed, what it adds to the final time, and the game to store after it.
 */
final readonly class RoomHint
{
    /**
     * @param int $penalty the seconds this hint adds to the final time
     */
    public function __construct(
        public HintResult $result,
        public int $penalty,
        public Game $game,
    ) {
    }
}
