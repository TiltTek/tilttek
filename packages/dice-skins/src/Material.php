<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

/**
 * What a die seems to be made of: a hint for the renderer, which draws each material its own way. The material never
 * carries the meaning of a face, and the high-contrast appearance shows every die as `Solid`.
 */
enum Material: string
{
    case Solid = 'solid';
    case Translucent = 'translucent';
    case Pearl = 'pearl';
    case Glitter = 'glitter';
    case Marble = 'marble';
    case Metal = 'metal';
    case Wood = 'wood';
    case Stone = 'stone';
    case Gem = 'gem';

    private const array WORDS = [
        'resin' => 'solid',
        'plastic' => 'solid',
        'acrylic' => 'solid',
        'glass' => 'translucent',
        'crystal' => 'gem',
        'bone' => 'stone',
    ];

    /**
     * The material a free text names — a material as a catalog of collectible dice writes it, `Resin` or `metal` —,
     * or `Solid` for a text that names none of them.
     */
    public static function fromText(?string $text): self
    {
        $word = strtolower(trim($text ?? ''));

        return self::tryFrom(self::WORDS[$word] ?? $word) ?? self::Solid;
    }
}
