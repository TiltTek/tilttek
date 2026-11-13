<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Exception;

use DomainException;

/**
 * The log of a table asked to be shown while its game goes on: the log holds the seed, which tells every draw to come,
 * so it is shown only once the game is over.
 */
final class GameInProgress extends DomainException implements GameEngineException
{
    public static function table(string $gameId): self
    {
        return new self(\sprintf('The game at the table "%s" is not over: its log, which holds the seed, stays secret until it is.', $gameId));
    }
}
