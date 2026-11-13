<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Exception;

use RuntimeException;

/**
 * A deck, or a version of it, was never saved.
 */
final class UnknownDeck extends RuntimeException implements DeckBuilderException
{
    public static function named(string $deck): self
    {
        return new self(\sprintf('The deck "%s" has no saved version.', $deck));
    }

    public static function version(string $deck, int $version): self
    {
        return new self(\sprintf('The deck "%s" has no version %d.', $deck, $version));
    }
}
