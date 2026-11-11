<?php

declare(strict_types=1);

namespace Tilttek\Quests\Exception;

use OutOfBoundsException;

/**
 * A duel is asked for by a key no duel has.
 */
final class UnknownDuel extends OutOfBoundsException implements QuestsException
{
    public static function keyed(string $key): self
    {
        return new self(\sprintf('No duel has the key "%s".', $key));
    }
}
