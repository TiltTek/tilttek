<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Internal;

use Tilttek\PlayLog\Exception\InvalidPlay;

/**
 * The shape of every identifier the library takes. An identifier is stable and opaque, never a name or an address: a
 * member's, a group's or a play's holds no "@". It starts with a letter, so that PHP never reads it as an integer key.
 * A game keeps the shape of the catalogue it comes from, where "@" is allowed.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string PLAIN = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';

    private const string GAME = '/^[A-Za-z][A-Za-z0-9_.:@-]{0,127}$/D';

    private function __construct()
    {
    }

    /**
     * @param string $what "a member", "a group" or "a play"
     *
     * @throws InvalidPlay when the value is not an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::PLAIN, $value)) {
            throw InvalidPlay::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_LENGTH));
        }

        return $value;
    }

    /**
     * @throws InvalidPlay when the value is not the identifier of a game of the catalogue
     */
    public static function game(string $value): string
    {
        if (1 !== preg_match(self::GAME, $value)) {
            throw InvalidPlay::because(\sprintf('The identifier of a game is 1 to %d letters, digits, "_", ".", ":", "@" or "-", starting with a letter.', self::MAX_LENGTH));
        }

        return $value;
    }
}
