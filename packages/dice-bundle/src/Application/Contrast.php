<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The contrast of two colours written `#rrggbb`, as WCAG 2 measures it — from 1:1 to 21:1 —, and the ink that reads on
 * a face: black or white, whichever contrasts more with it, which is always at least 4.5:1.
 */
final class Contrast
{
    public const string BLACK = '#000000';

    public const string WHITE = '#ffffff';

    private function __construct()
    {
    }

    /**
     * The contrast of two colours, the lighter's relative luminance plus 0.05 over the darker's plus 0.05.
     */
    public static function ratio(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Black or white, whichever contrasts more with the face.
     */
    public static function readableInk(string $face): string
    {
        $luminance = self::luminance($face);

        return ($luminance + 0.05) / 0.05 >= 1.05 / ($luminance + 0.05) ? self::BLACK : self::WHITE;
    }

    /**
     * The relative luminance of a colour written `#rrggbb`: its sRGB channels made linear and weighted.
     *
     * @throws InvalidDefinition when the colour is not written `#rrggbb`
     */
    public static function luminance(string $colour): float
    {
        if (1 !== preg_match('/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/D', $colour, $match)) {
            throw InvalidDefinition::because(\sprintf('A colour is written #rrggbb; "%s" is not.', $colour));
        }

        return 0.2126 * self::linear($match[1]) + 0.7152 * self::linear($match[2]) + 0.0722 * self::linear($match[3]);
    }

    /**
     * A channel written in two hexadecimal digits, made linear: the sRGB curve, straight up to 10 of 255.
     */
    private static function linear(string $hex): float
    {
        $channel = (int) hexdec($hex);

        return $channel <= 10 ? $channel / 255 / 12.92 : (($channel / 255 + 0.055) / 1.055) ** 2.4;
    }
}
