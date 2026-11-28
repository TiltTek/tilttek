<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Internal;

use Tilttek\PlayLog\Exception\InvalidPlay;

/**
 * The one line of text a play holds, its place: valid UTF-8, trimmed, within its length in characters, without control
 * characters and without the characters that reverse the direction of a text.
 *
 * @internal
 */
final class Text
{
    /** The control characters, and the embeddings, overrides and isolates of bidirectional text. */
    private const string FORBIDDEN = '/[\p{Cc}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    private function __construct()
    {
    }

    /**
     * @throws InvalidPlay when the text is empty, too long or holds a forbidden character
     */
    public static function line(string $value, int $max, string $what): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw InvalidPlay::because(\sprintf('The %s is not valid UTF-8.', $what));
        }

        $value = trim($value);
        $length = mb_strlen($value);

        if (0 === $length || $length > $max) {
            throw InvalidPlay::because(\sprintf('The %s is 1 to %d characters.', $what, $max));
        }

        if (1 === preg_match(self::FORBIDDEN, $value)) {
            throw InvalidPlay::because(\sprintf('The %s holds a control character or a direction override.', $what));
        }

        return $value;
    }
}
