<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * The pattern every name of the library follows — identifiers of cards, players and games, keys of faces, names of
 * piles: 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit. A name is
 * opaque: the library never reads a meaning into it, and it never holds a person's data.
 *
 * @internal
 */
final class Name
{
    public const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D';

    /**
     * @throws InvalidDefinition
     */
    public static function check(string $name, string $what): string
    {
        if (1 !== preg_match(self::PATTERN, $name)) {
            throw InvalidDefinition::because(\sprintf('%s is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit.', $what));
        }

        return $name;
    }
}
