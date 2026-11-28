<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Internal;

use Tilttek\GameCatalog\Exception\InvalidEntry;

/**
 * The text an entry keeps: valid UTF-8, trimmed, within its length in characters, and without control characters but
 * the line breaks a long text may hold.
 *
 * @internal
 */
final class Text
{
    private function __construct()
    {
    }

    /**
     * One line: a name, a publisher, a location in a book.
     *
     * @throws InvalidEntry when the text is empty, too long or holds a control character
     */
    public static function line(string $value, int $max, string $what): string
    {
        return self::check($value, $max, $what, '/\p{Cc}/u');
    }

    /**
     * Several lines: a description, the text of an erratum.
     *
     * @throws InvalidEntry when the text is empty, too long or holds a control character other than a line break
     */
    public static function lines(string $value, int $max, string $what): string
    {
        return self::check(str_replace("\r\n", "\n", $value), $max, $what, '/[^\P{Cc}\n]/u');
    }

    private static function check(string $value, int $max, string $what, string $forbidden): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw InvalidEntry::because(\sprintf('The %s is not valid UTF-8.', $what));
        }

        $value = trim($value);
        $length = mb_strlen($value);

        if (0 === $length || $length > $max) {
            throw InvalidEntry::because(\sprintf('The %s is 1 to %d characters.', $what, $max));
        }

        if (1 === preg_match($forbidden, $value)) {
            throw InvalidEntry::because(\sprintf('The %s holds a control character.', $what));
        }

        return $value;
    }
}
