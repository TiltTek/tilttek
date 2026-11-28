<?php

declare(strict_types=1);

namespace Tilttek\Factions\Internal;

use Tilttek\Factions\Exception\InvalidRoster;

/**
 * The shape of every identifier and key the library takes. An identifier is stable and opaque, never a name or an
 * address: it holds no "@". It starts with a letter, so that PHP never reads it as an integer key. A key names a
 * faction, a rank or a tier within one roster, and is shorter.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    public const int MAX_KEY_LENGTH = 64;

    private const string IDENTIFIER = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';

    private const string KEY = '/^[A-Za-z][A-Za-z0-9_.:-]{0,63}$/D';

    private function __construct()
    {
    }

    /**
     * @param string $what "a campaign", "a member", "a character", "a session"
     *
     * @throws InvalidRoster when the value is not an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::IDENTIFIER, $value)) {
            throw InvalidRoster::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_LENGTH));
        }

        return $value;
    }

    /**
     * @param string $what "a faction", "a rank", "a tier"
     *
     * @throws InvalidRoster when the value is not a key
     */
    public static function key(string $value, string $what): string
    {
        if (1 !== preg_match(self::KEY, $value)) {
            throw InvalidRoster::because(\sprintf('The key of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_KEY_LENGTH));
        }

        return $value;
    }

    /**
     * The first key that comes twice, or null.
     *
     * @param list<string> $keys
     */
    public static function twice(array $keys): ?string
    {
        $seen = [];

        foreach ($keys as $key) {
            if (isset($seen[$key])) {
                return $key;
            }

            $seen[$key] = true;
        }

        return null;
    }
}
