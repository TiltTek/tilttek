<?php

declare(strict_types=1);

namespace Tilttek\Factions\Internal;

use Tilttek\Factions\Exception\InvalidRoster;

/**
 * The text a roster holds: valid UTF-8, trimmed, within its length in characters, without the characters that reverse
 * the direction of a text. A line holds no control character; a block may hold line breaks and tabulations, its line
 * ends written "\n".
 *
 * @internal
 */
final class Text
{
    /** The control characters, and the embeddings, overrides and isolates of bidirectional text. */
    private const string LINE = '/[\p{Cc}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /** The same, line breaks and tabulations aside. */
    private const string BLOCK = '/[^\P{Cc}\t\n]|[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    private function __construct()
    {
    }

    /**
     * One line, empty unless required.
     *
     * @throws InvalidRoster when the text is not valid UTF-8, too long, empty while required, or holds a forbidden
     *                       character
     */
    public static function line(string $value, int $max, string $what, bool $required = true): string
    {
        return self::checked(self::trimmed($value, $what), $max, $what, $required, self::LINE);
    }

    /**
     * Lines of text, possibly empty.
     *
     * @throws InvalidRoster when the text is not valid UTF-8, too long, or holds a forbidden character
     */
    public static function block(string $value, int $max, string $what): string
    {
        return self::checked(str_replace(["\r\n", "\r"], "\n", self::trimmed($value, $what)), $max, $what, false, self::BLOCK);
    }

    private static function trimmed(string $value, string $what): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw InvalidRoster::because(\sprintf('The %s is not valid UTF-8.', $what));
        }

        return trim($value);
    }

    private static function checked(string $value, int $max, string $what, bool $required, string $forbidden): string
    {
        $length = mb_strlen($value);

        if ($length > $max || ($required && 0 === $length)) {
            throw InvalidRoster::because(\sprintf('The %s is %d to %d characters.', $what, $required ? 1 : 0, $max));
        }

        if (1 === preg_match($forbidden, $value)) {
            throw InvalidRoster::because(\sprintf('The %s holds a control character or a direction override.', $what));
        }

        return $value;
    }
}
