<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Exception;

use RuntimeException;

/**
 * A deck names a format the application's format catalog does not hold.
 */
final class UnknownFormat extends RuntimeException implements DeckBuilderException
{
    public static function named(string $format): self
    {
        return new self(\sprintf('The format "%s" is not in the format catalog.', $format));
    }
}
