<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

use Tilttek\Celebrations\Exception\InvalidCelebration;

/**
 * The checks of the keys and values the library keeps.
 *
 * @internal
 */
final class Text
{
    /** A key: 1 to 96 lowercase letters, digits, `_`, `-`, or single dots between them. */
    private const string KEY = '/^(?=.{1,96}\z)[a-z0-9_-]+(?:\.[a-z0-9_-]+)*\z/';

    /** An identifier: 1 to 191 visible ASCII characters, no space. */
    private const string IDENTIFIER = '/^[\x21-\x7E]{1,191}\z/';

    /**
     * @throws InvalidCelebration
     */
    public static function key(string $key, string $what): string
    {
        if (1 !== preg_match(self::KEY, $key)) {
            throw InvalidCelebration::because(\sprintf('The key of %s must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "%s" is not.', $what, $key));
        }

        return $key;
    }

    /**
     * @throws InvalidCelebration
     */
    public static function identifier(string $identifier, string $what): string
    {
        if (1 !== preg_match(self::IDENTIFIER, $identifier)) {
            throw InvalidCelebration::because(\sprintf('The %s of an achievement is 1 to 191 visible ASCII characters, without spaces.', $what));
        }

        return $identifier;
    }

    /**
     * A parameter's text: 1 to `$max` characters of UTF-8 without control characters nor surrounding spaces.
     *
     * @throws InvalidCelebration
     */
    public static function value(string $value, string $what, int $max): string
    {
        if (1 !== preg_match('/^\P{Cc}{1,'.$max.'}\z/u', $value) || trim($value) !== $value) {
            throw InvalidCelebration::because(\sprintf('%s is 1 to %d characters of UTF-8 without control characters nor surrounding spaces.', $what, $max));
        }

        return $value;
    }
}
