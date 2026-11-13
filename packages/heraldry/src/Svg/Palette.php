<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Svg;

use Tilttek\Heraldry\Exception\InvalidPalette;
use Tilttek\Heraldry\Tincture;

/**
 * The colour each tincture is drawn in, and the colour of the outlines. Furs are drawn from their metal and colour:
 * ermine as sable spots on argent, vair as argent and azure bells.
 */
final readonly class Palette
{
    private const array DEFAULT = [
        'or' => '#f2c230',
        'argent' => '#f7f7f2',
        'gules' => '#c4161c',
        'azure' => '#1d4f9a',
        'vert' => '#1b7a3c',
        'sable' => '#1c1c1c',
        'purpure' => '#6b2d73',
    ];

    /** @var array<string, string> */
    private array $colours;

    /**
     * @param array<string, string> $colours by tincture value — `or`, `argent`, `gules`, `azure`, `vert`, `sable`,
     *                                       `purpure` —, each `#rrggbb`; those left out keep their default
     * @param string                $outline the colour of the outlines, `#rrggbb`
     *
     * @throws InvalidPalette when a key is not a metal or a colour, or a value not `#rrggbb`
     */
    public function __construct(
        array $colours = [],
        public string $outline = '#1c1c1c',
    ) {
        foreach ($colours as $tincture => $colour) {
            if (!isset(self::DEFAULT[$tincture])) {
                throw new InvalidPalette(\sprintf('A palette gives the colours of the metals and colours; %s is not one.', $tincture));
            }

            $this->check($colour);
        }

        $this->check($outline);
        $this->colours = $colours + self::DEFAULT;
    }

    /**
     * The colour of a metal or a colour; the light ground of a fur.
     */
    public function colour(Tincture $tincture): string
    {
        return $this->colours[match ($tincture) {
            Tincture::Ermine, Tincture::Vair => 'argent',
            default => $tincture->value,
        }];
    }

    /**
     * @throws InvalidPalette
     */
    private function check(string $colour): void
    {
        if (1 !== preg_match('/\A#[0-9a-fA-F]{6}\z/', $colour)) {
            throw new InvalidPalette(\sprintf('A colour is written #rrggbb; %s is not.', $colour));
        }
    }
}
