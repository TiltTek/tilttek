<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Exception;

use RuntimeException;

/**
 * A version saved at the same time by another request, or a copy given the identifier of a deck that exists.
 */
final class DeckConflict extends RuntimeException implements DeckBuilderException
{
    public static function version(string $deck, int $version): self
    {
        return new self(\sprintf('The deck "%s" already has a version %d: read the deck again and save it once more.', $deck, $version));
    }

    public static function exists(string $deck): self
    {
        return new self(\sprintf('The deck "%s" already exists: a copy takes a new identifier.', $deck));
    }
}
