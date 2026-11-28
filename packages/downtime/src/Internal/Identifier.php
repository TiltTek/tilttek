<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Internal;

use Tilttek\Downtime\Exception\InvalidDowntime;

/**
 * The shape of every identifier and key the library takes. An identifier is stable and opaque, never a name or an
 * address: it holds no "@". It starts with a letter, so that PHP never reads it as an integer key. A key names an
 * activity, an outcome or a resource within the rules of one system, and is shorter.
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
     * @param string $what "an entry", "a campaign", "a character", "a member", "a system"
     *
     * @throws InvalidDowntime when the value is not an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::IDENTIFIER, $value)) {
            throw InvalidDowntime::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_LENGTH));
        }

        return $value;
    }

    /**
     * @param string $what "an activity", "an outcome", "a resource"
     *
     * @throws InvalidDowntime when the value is not a key
     */
    public static function key(string $value, string $what): string
    {
        if (1 !== preg_match(self::KEY, $value)) {
            throw InvalidDowntime::because(\sprintf('The key of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_KEY_LENGTH));
        }

        return $value;
    }

    /**
     * The first value that comes twice in the list, or null.
     *
     * @param list<string> $values
     */
    public static function twice(array $values): ?string
    {
        $seen = [];

        foreach ($values as $value) {
            if (isset($seen[$value])) {
                return $value;
            }

            $seen[$value] = true;
        }

        return null;
    }
}
