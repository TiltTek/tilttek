<?php

declare(strict_types=1);

namespace Tilttek\Party;

use Tilttek\Party\Exception\InvalidParty;

/**
 * The checks of the texts and keys the library keeps.
 *
 * @internal
 */
final class Text
{
    /** A key: 1 to 96 lowercase letters, digits, `_`, `-`, or single dots between them. */
    private const string KEY = '/^(?=.{1,96}\z)[a-z0-9_-]+(?:\.[a-z0-9_-]+)*\z/';

    /**
     * @throws InvalidParty
     */
    public static function key(string $key, string $what): string
    {
        if (1 !== preg_match(self::KEY, $key)) {
            throw InvalidParty::because(\sprintf('The key of %s must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "%s" is not.', $what, $key));
        }

        return $key;
    }

    /**
     * A currency: a key that starts with a letter.
     *
     * @throws InvalidParty
     */
    public static function currency(string $currency): string
    {
        if (1 !== preg_match('/^[a-z]/', self::key($currency, 'a currency'))) {
            throw InvalidParty::because(\sprintf('A currency key starts with a letter; "%s" does not.', $currency));
        }

        return $currency;
    }

    /**
     * A name: 1 to `$max` characters of UTF-8 without control characters nor surrounding spaces.
     *
     * @throws InvalidParty
     */
    public static function name(string $name, string $what, int $max = 100): string
    {
        if (1 !== preg_match('/^\P{Cc}{1,'.$max.'}\z/u', $name) || trim($name) !== $name) {
            throw InvalidParty::because(\sprintf('%s is 1 to %d characters of UTF-8 without control characters nor surrounding spaces.', $what, $max));
        }

        return $name;
    }

    /**
     * A text of several lines: 1 to `$max` characters of UTF-8, line breaks and tabs the only control characters, not
     * blank.
     *
     * @throws InvalidParty
     */
    public static function body(string $text, string $what, int $max): string
    {
        if (1 !== preg_match('/^[^\p{Cc}]*+(?:[\n\t][^\p{Cc}]*+)*+\z/u', $text) || '' === trim($text) || mb_strlen($text) > $max) {
            throw InvalidParty::because(\sprintf('%s is 1 to %d characters of UTF-8, not blank, with line breaks and tabs the only control characters.', $what, $max));
        }

        return $text;
    }
}
