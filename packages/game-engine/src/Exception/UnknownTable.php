<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Exception;

use OutOfBoundsException;

/**
 * A table or a game asked for by an identifier or a name none has.
 */
final class UnknownTable extends OutOfBoundsException implements GameEngineException
{
    public static function table(string $gameId): self
    {
        return new self(\sprintf('No table has the identifier "%s".', $gameId));
    }

    public static function game(string $name): self
    {
        return new self(\sprintf('No game is named "%s".', $name));
    }
}
