<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Exception;

use DomainException;
use Tilttek\EscapeRoom\Exception\EscapeRoomException;

/**
 * A move the desk refuses before the game is read: a game unknown or already there, a room unknown, an identifier the
 * bundle does not take.
 */
final class DeskRefused extends DomainException implements EscapeRoomException
{
    public static function unknownGame(string $id): self
    {
        return new self(\sprintf('No game "%s".', mb_substr($id, 0, 64)));
    }

    public static function unknownRoom(string $id): self
    {
        return new self(\sprintf('No room "%s".', mb_substr($id, 0, 64)));
    }

    public static function exists(string $id): self
    {
        return new self(\sprintf('The game "%s" is there already.', $id));
    }

    public static function identifier(): self
    {
        return new self('The identifier of a game holds 1 to 64 characters among A-Z, a-z, 0-9, - and _, from a letter or a digit.');
    }
}
