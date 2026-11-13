<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Exception;

use InvalidArgumentException;

/**
 * A drawing option the renderer refuses: a prefix of ids that is not a letter followed by letters, digits, `-` or `_`.
 */
final class InvalidDrawing extends InvalidArgumentException implements HeraldryException
{
    public static function id(): self
    {
        return new self('The prefix of the ids is a letter followed by up to 47 letters, digits, - or _.');
    }
}
