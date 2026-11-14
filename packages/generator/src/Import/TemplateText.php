<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import;

/**
 * Writes the parts of a generated template: plain text that Twig prints as written, and strings in Twig's syntax.
 *
 * @internal
 */
final class TemplateText
{
    private function __construct()
    {
    }

    /**
     * Text printed as written: as it is, unless a brace could open a Twig tag, then as a printed string.
     */
    public static function literal(string $text): string
    {
        if ('' === $text || (!str_contains($text, '{') && !str_contains($text, '}'))) {
            return $text;
        }

        return '{{ '.self::string($text).' }}';
    }

    /**
     * A string in Twig's syntax, between single quotes, which Twig does not interpolate.
     */
    public static function string(string $text): string
    {
        return "'".strtr($text, ['\\' => '\\\\', "'" => "\\'"])."'";
    }
}
