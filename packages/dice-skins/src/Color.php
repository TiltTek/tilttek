<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

use Tilttek\DiceSkins\Exception\InvalidSkin;

/**
 * An sRGB colour, read from `#rgb` or `#rrggbb` and written `#rrggbb` in lower case, with its relative luminance and
 * its contrast ratio against another colour, as WCAG 2.2 defines them.
 */
final readonly class Color
{
    /**
     * The contrast WCAG 2.2 asks of text at level AA, 4.5:1: the numbers and pips of a face.
     */
    public const float AA = 4.5;

    /**
     * The contrast WCAG 2.2 asks of text at level AAA, 7:1: what the high-contrast appearance keeps.
     */
    public const float AAA = 7.0;

    private function __construct(
        public string $hex,
    ) {
    }

    /**
     * @throws InvalidSkin when the text is not `#rgb` nor `#rrggbb`
     */
    public static function fromHex(string $text): self
    {
        if (1 !== preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/D', $text)) {
            throw InvalidSkin::because(\sprintf('A colour is written #rgb or #rrggbb; "%s" is not.', mb_substr($text, 0, 16)));
        }

        $digits = strtolower(substr($text, 1));

        if (3 === \strlen($digits)) {
            $digits = $digits[0].$digits[0].$digits[1].$digits[1].$digits[2].$digits[2];
        }

        return new self('#'.$digits);
    }

    public static function black(): self
    {
        return new self('#000000');
    }

    public static function white(): self
    {
        return new self('#ffffff');
    }

    /**
     * The relative luminance, from 0 for black to 1 for white.
     */
    public function luminance(): float
    {
        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($this->hex, 1), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * The contrast ratio of the two colours, from 1 for the same colour to 21 for black on white, never rounded.
     */
    public function contrastWith(self $other): float
    {
        $mine = $this->luminance();
        $theirs = $other->luminance();

        return (max($mine, $theirs) + 0.05) / (min($mine, $theirs) + 0.05);
    }

    /**
     * Black or white, whichever stands out more on this colour; black when both stand out as much.
     */
    public function bestInk(): self
    {
        $white = self::white();

        return $this->contrastWith($white) > $this->contrastWith(self::black()) ? $white : self::black();
    }

    public function equals(self $other): bool
    {
        return $this->hex === $other->hex;
    }
}
