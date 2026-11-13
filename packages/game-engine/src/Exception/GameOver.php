<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Exception;

use DomainException;

/**
 * A move taken back at a table whose game is over: once it is, its log and seed may be shown to anyone, and the seed
 * tells every draw to come, so no move is taken back to play again with that knowledge.
 */
final class GameOver extends DomainException implements GameEngineException
{
    public static function table(string $gameId): self
    {
        return new self(\sprintf('The game at the table "%s" is over: its seed may be shown, so no move is taken back.', $gameId));
    }
}
