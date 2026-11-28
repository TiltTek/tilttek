<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Internal;

use Tilttek\Encounter\Exception\InvalidEncounter;

/**
 * The shape of every identifier the library takes. An identifier is stable and opaque, never a name or an address: it
 * holds no "@". It starts with a letter, so that PHP never reads it as an integer key.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string PLAIN = '/^[A-Za-z][A-Za-z0-9_.:-]*$/D';

    private function __construct()
    {
    }

    /**
     * @param string $what "an encounter", "a member", "a participant"...
     * @param int    $max  the most characters of the identifier
     *
     * @throws InvalidEncounter when the value is not an identifier
     */
    public static function check(string $value, string $what, int $max = self::MAX_LENGTH): string
    {
        if (\strlen($value) > $max || 1 !== preg_match(self::PLAIN, $value)) {
            throw InvalidEncounter::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, $max));
        }

        return $value;
    }
}
