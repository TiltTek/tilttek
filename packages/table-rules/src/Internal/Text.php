<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Internal;

use Tilttek\TableRules\Exception\InvalidRules;

/**
 * The shape of the text the library keeps: valid UTF-8, without control characters nor the characters that reorder
 * the text shown, its length counted in code points.
 *
 * @internal
 */
final class Text
{
    /** The control characters but the tab and the line feed, and the bidirectional embeddings, overrides and isolates. */
    private const string FORBIDDEN = '/[\x{0}-\x{8}\x{B}-\x{1F}\x{7F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    private function __construct()
    {
    }

    /**
     * One line of text, trimmed.
     *
     * @param string $what "the title of a rule"...
     *
     * @throws InvalidRules when the text is not valid UTF-8, holds a control character or a line break, is empty or too long
     */
    public static function line(string $value, int $max, string $what): string
    {
        $line = trim(self::checked($value, $what), " \t");
        if (str_contains($line, "\n")) {
            throw InvalidRules::because(\sprintf('%s is one line.', ucfirst($what)));
        }

        return self::bounded($line, $max, $what);
    }

    /**
     * Lines of text, their breaks made line feeds, each line without trailing blanks, the blank lines around them removed.
     *
     * @param string $what "the text of a rule"...
     *
     * @throws InvalidRules when the text is not valid UTF-8, holds a control character, is empty, too long or of too many lines
     */
    public static function block(string $value, int $max, int $maxLines, string $what): string
    {
        $lines = array_map(rtrim(...), explode("\n", self::checked($value, $what)));
        $block = trim(implode("\n", $lines), "\n");
        if (substr_count($block, "\n") >= $maxLines) {
            throw InvalidRules::because(\sprintf('%s is %d lines at most.', ucfirst($what), $maxLines));
        }

        return self::bounded($block, $max, $what);
    }

    /**
     * The number of code points of a valid UTF-8 text.
     */
    public static function length(string $value): int
    {
        return (int) preg_match_all('/./su', $value);
    }

    private static function checked(string $value, string $what): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if (1 !== preg_match('//u', $value)) {
            throw InvalidRules::because(\sprintf('%s is not valid UTF-8.', ucfirst($what)));
        }

        if (1 === preg_match(self::FORBIDDEN, $value)) {
            throw InvalidRules::because(\sprintf('%s holds a control character.', ucfirst($what)));
        }

        return $value;
    }

    private static function bounded(string $value, int $max, string $what): string
    {
        if ('' === $value) {
            throw InvalidRules::because(\sprintf('%s is empty.', ucfirst($what)));
        }

        if (self::length($value) > $max) {
            throw InvalidRules::because(\sprintf('%s is %d characters at most.', ucfirst($what), $max));
        }

        return $value;
    }
}
