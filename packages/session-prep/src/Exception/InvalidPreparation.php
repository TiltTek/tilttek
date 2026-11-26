<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Exception;

use InvalidArgumentException;

/**
 * A value of a preparation, of one of its parts or of a call is out of its shape or its bounds, or names a part the
 * preparation does not hold.
 */
final class InvalidPreparation extends InvalidArgumentException implements SessionPrepException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
