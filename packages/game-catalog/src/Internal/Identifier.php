<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Internal;

use Tilttek\GameCatalog\Exception\InvalidEntry;

/**
 * The shape of every identifier the library takes: an entry, a member, a correction, an erratum, a source, a system or
 * a cover. An identifier is stable and opaque, never a name. It starts with a letter, so that PHP never reads it as an
 * integer key.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string PATTERN = '/^[A-Za-z][A-Za-z0-9_.:@-]{0,127}$/D';

    private function __construct()
    {
    }

    /**
     * @throws InvalidEntry when the value is not an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidEntry::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":", "@" or "-", starting with a letter.', $what, self::MAX_LENGTH));
        }

        return $value;
    }
}
