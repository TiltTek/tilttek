<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Internal;

use Tilttek\TurnClock\Exception\InvalidClock;

/**
 * The shape of every identifier the library takes. An identifier is stable and opaque, never a name or an address: it
 * holds no "@". It starts with a letter, so that PHP never reads it as an integer key.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string PLAIN = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';

    private function __construct()
    {
    }

    /**
     * @param string $what "a player" or "a clock"
     *
     * @throws InvalidClock when the value is not an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::PLAIN, $value)) {
            throw InvalidClock::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_LENGTH));
        }

        return $value;
    }
}
