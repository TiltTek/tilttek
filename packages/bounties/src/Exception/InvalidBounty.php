<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Exception;

use InvalidArgumentException;
use Phil\Resource\ResourceReference;

/**
 * A value the library does not take: an identifier, a prize, a length, a time, a record read back from storage, or a
 * reference of the kind `erased`, which the library draws for an erased member and never takes from a caller.
 */
final class InvalidBounty extends InvalidArgumentException implements BountiesException
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
