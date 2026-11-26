<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a title, a text, an attachment, a media type, a cue, an audience or a limit out of
 * bounds.
 */
final class InvalidHandout extends InvalidArgumentException implements HandoutException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
