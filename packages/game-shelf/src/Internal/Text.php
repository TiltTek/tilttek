<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Internal;

use Tilttek\GameShelf\Exception\InvalidShelf;

/**
 * The texts a member writes on their shelf: valid UTF-8, trimmed, within their length in characters, without control
 * characters — but the line breaks of a comment — and without the characters that reverse the direction of a text.
 *
 * @internal
 */
final class Text
{
    /** The control characters, and the embeddings, overrides and isolates of bidirectional text. */
    private const string LINE = '/[\p{Cc}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /** The same, but the line break. */
    private const string LINES = '/[^\P{Cc}\n]|[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    private function __construct()
    {
    }

    /**
     * One line: an edition, a location, a tag.
     *
     * @throws InvalidShelf when the text is empty, too long or holds a forbidden character
     */
    public static function line(string $value, int $max, string $what): string
    {
        return self::check($value, $max, $what, self::LINE);
    }

    /**
     * Several lines: a comment.
     *
     * @throws InvalidShelf when the text is empty, too long or holds a forbidden character other than a line break
     */
    public static function lines(string $value, int $max, string $what): string
    {
        return self::check(str_replace("\r\n", "\n", $value), $max, $what, self::LINES);
    }

    private static function check(string $value, int $max, string $what, string $forbidden): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw InvalidShelf::because(\sprintf('The %s is not valid UTF-8.', $what));
        }

        $value = trim($value);
        $length = mb_strlen($value);

        if (0 === $length || $length > $max) {
            throw InvalidShelf::because(\sprintf('The %s is 1 to %d characters.', $what, $max));
        }

        if (1 === preg_match($forbidden, $value)) {
            throw InvalidShelf::because(\sprintf('The %s holds a control character or a direction override.', $what));
        }

        return $value;
    }
}
