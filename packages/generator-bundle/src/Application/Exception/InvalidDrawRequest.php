<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A draw asked of the API with a count, a uniqueness, a seed or a state it cannot read.
 */
final class InvalidDrawRequest extends InvalidArgumentException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
