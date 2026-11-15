<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Exception;

use InvalidArgumentException;
use Phil\Resource\ResourceReference;

/**
 * A value the library does not take: an identifier, a title, a prize, a time, a record read back from storage, or a
 * reference of the kind `erased`, which the library draws for an erased member and never takes from a caller.
 */
final class InvalidChallenge extends InvalidArgumentException implements FriendChallengesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function erased(string $what, ResourceReference $reference): self
    {
        return new self(\sprintf('%s is %s, a pseudonym the library draws for an erased member; it is never taken from a caller.', $what, $reference->toString()));
    }
}
