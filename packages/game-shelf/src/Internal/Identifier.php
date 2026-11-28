<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Internal;

use Tilttek\GameShelf\Exception\InvalidShelf;

/**
 * The shape of every identifier the library takes. An identifier is stable and opaque, never a name or an address: a
 * member's holds no "@". It starts with a letter, so that PHP never reads it as an integer key. A game keeps the shape
 * of the catalogue it comes from, where "@" is allowed, and so does a copy, whose identifier may be its game's.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string MEMBER = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';

    private const string GAME = '/^[A-Za-z][A-Za-z0-9_.:@-]{0,127}$/D';

    private function __construct()
    {
    }

    /**
     * @throws InvalidShelf when the value is not the identifier of a member
     */
    public static function member(string $value): string
    {
        if (1 !== preg_match(self::MEMBER, $value)) {
            throw InvalidShelf::because(\sprintf('The identifier of a member is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', self::MAX_LENGTH));
        }

        return $value;
    }

    /**
     * @param string $what "a game" or "a copy"
     *
     * @throws InvalidShelf when the value is not the identifier of a game of the catalogue, or of a copy
     */
    public static function game(string $value, string $what = 'a game'): string
    {
        if (1 !== preg_match(self::GAME, $value)) {
            throw InvalidShelf::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":", "@" or "-", starting with a letter.', $what, self::MAX_LENGTH));
        }

        return $value;
    }
}
