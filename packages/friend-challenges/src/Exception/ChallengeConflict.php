<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Exception;

use RuntimeException;

/**
 * Another writer changed the challenge first: the store refuses a version that is no longer the last one, and the
 * library tries three times before it gives up.
 */
final class ChallengeConflict extends RuntimeException implements FriendChallengesException
{
    public static function exists(string $id): self
    {
        return new self(\sprintf('A challenge "%s" is stored already.', $id));
    }

    public static function stale(string $id, int $expected): self
    {
        return new self(\sprintf('The challenge "%s" is no longer at version %d.', $id, $expected));
    }

    public static function busy(string $id, int $attempts): self
    {
        return new self(\sprintf('The challenge "%s" changed under every one of %d attempts.', $id, $attempts));
    }
}
