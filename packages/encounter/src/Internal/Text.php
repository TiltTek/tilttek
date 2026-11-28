<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Internal;

use Tilttek\Encounter\Exception\InvalidEncounter;

/**
 * The shape of the text the library keeps: one line of valid UTF-8, without control characters nor the characters
 * that reorder the text shown, its length counted in code points.
 *
 * @internal
 */
final class Text
{
    /** The control characters, and the bidirectional embeddings, overrides and isolates. */
    private const string FORBIDDEN = '/[\x{0}-\x{1F}\x{7F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    private function __construct()
    {
    }

    /**
     * One line of text, trimmed of its spaces.
     *
     * @param string $what "the title of an encounter"...
     *
     * @throws InvalidEncounter when the text is not valid UTF-8, holds a control character or a line break, is empty or too long
     */
    public static function line(string $value, int $max, string $what): string
    {
        if (1 !== preg_match('//u', $value)) {
            throw InvalidEncounter::because(\sprintf('%s is not valid UTF-8.', ucfirst($what)));
        }

        if (1 === preg_match(self::FORBIDDEN, $value)) {
            throw InvalidEncounter::because(\sprintf('%s is one line without control characters.', ucfirst($what)));
        }

        $line = trim($value, ' ');
        if ('' === $line) {
            throw InvalidEncounter::because(\sprintf('%s is empty.', ucfirst($what)));
        }

        if ((int) preg_match_all('/./su', $line) > $max) {
            throw InvalidEncounter::because(\sprintf('%s is %d characters at most.', ucfirst($what), $max));
        }

        return $line;
    }
}
