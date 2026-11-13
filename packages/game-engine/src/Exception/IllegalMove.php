<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Exception;

use DomainException;

/**
 * A move the table refuses in its current state: not the seat's turn, a phase change the machine does not allow, a
 * move the game refuses, a game already over, or an undo that is not allowed.
 */
final class IllegalMove extends DomainException implements GameEngineException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
