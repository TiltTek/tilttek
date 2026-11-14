<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

/**
 * The appearance a die of a member is shown with, ready for the renderer: the name of its skin or of the die owned,
 * its look, where it comes from, and whether it was adapted for high contrast. The appearance never changes the
 * randomness: it only dresses the dice the server rolled.
 *
 * The renderer reads it as CSS custom properties, `--tilttek-dice-color` for the faces and `--tilttek-dice-ink` for
 * the numbers, and as data attributes for the rest. Every value is a validated colour, a material, a number or a
 * texture key, except the name, which the template escapes as it escapes any attribute.
 */
final readonly class DieAppearance
{
    public function __construct(
        public int $sides,
        public string $name,
        public SkinLook $look,
        public AppearanceSource $source,
        public ?string $skin = null,
        public ?string $item = null,
        public bool $highContrast = false,
    ) {
    }

    /**
     * @return array{'--tilttek-dice-color': string, '--tilttek-dice-ink': string}
     */
    public function cssProperties(): array
    {
        return ['--tilttek-dice-color' => $this->look->face->hex, '--tilttek-dice-ink' => $this->look->ink->hex];
    }

    /**
     * The custom properties as the value of a `style` attribute.
     */
    public function style(): string
    {
        return \sprintf('--tilttek-dice-color: %s; --tilttek-dice-ink: %s', $this->look->face->hex, $this->look->ink->hex);
    }

    /**
     * @return array<string, string>
     */
    public function dataAttributes(): array
    {
        $attributes = [
            'data-dice-appearance' => $this->name,
            'data-dice-sides' => (string) $this->sides,
            'data-dice-material' => $this->look->material->value,
        ];

        if ($this->look->texture instanceof TextureReference) {
            $attributes['data-dice-texture'] = $this->look->texture->key;
        }

        if ($this->highContrast) {
            $attributes['data-dice-contrast'] = 'high';
        }

        return $attributes;
    }

    /**
     * The appearance as plain values, for a renderer that reads JSON.
     *
     * @return array{sides: int, name: string, source: string, skin: string|null, item: string|null, high_contrast: bool, contrast: float, face: string, ink: string, material: string, texture?: string}
     */
    public function toArray(): array
    {
        return [
            'sides' => $this->sides,
            'name' => $this->name,
            'source' => $this->source->value,
            'skin' => $this->skin,
            'item' => $this->item,
            'high_contrast' => $this->highContrast,
            'contrast' => round($this->look->contrast(), 2),
            ...$this->look->toArray(),
        ];
    }
}
