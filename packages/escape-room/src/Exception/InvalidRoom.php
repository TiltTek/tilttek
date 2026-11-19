<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Exception;

use InvalidArgumentException;

/**
 * A room, a session or a stored game the library does not take: a field missing, malformed or out of bounds.
 */
final class InvalidRoom extends InvalidArgumentException implements EscapeRoomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * A reference of the kind `erased` given by the caller: only an erasure writes one, in place of a member.
     *
     * @param string $role what the reference stands for: a team, a party, a host
     */
    public static function erased(string $role): self
    {
        return new self(\sprintf('A %s is never an erased reference: only an erasure writes one.', $role));
    }
}
