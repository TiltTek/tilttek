<?php

declare(strict_types=1);

namespace Tilttek\Rating;

use Tilttek\Rating\Exception\InvalidRating;

/**
 * The shape of every identifier the library takes: a game, a member or a result. An identifier is stable and opaque,
 * never a name, so that no event, no log line and no public standing carries what identifies a person.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:@-]{0,127}$/D';

    private function __construct()
    {
    }

    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidRating::because(\sprintf('The identifier of a %s is 1 to %d letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.', $what, self::MAX_LENGTH));
        }

        return $value;
    }
}
