<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Exception;

use InvalidArgumentException;

/**
 * A coat of arms the model cannot hold: charges an ordinary cannot carry or surround, too many charges, or a divided
 * field of one tincture.
 */
final class InvalidCoat extends InvalidArgumentException implements HeraldryException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
