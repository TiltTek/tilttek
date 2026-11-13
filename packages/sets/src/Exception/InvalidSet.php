<?php

declare(strict_types=1);

namespace Tilttek\Sets\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a code, a member, a tier, a reward, a holder or holdings it cannot take, or a set
 * definition that does not read.
 */
final class InvalidSet extends InvalidArgumentException implements SetsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
