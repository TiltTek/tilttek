<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

/**
 * How the faces of a die look: the colour of the faces, the colour of their numbers and pips — the ink —, the
 * material, and the texture the renderer lays on the faces, if any.
 */
final readonly class SkinLook
{
    public function __construct(
        public Color $face,
        public Color $ink,
        public Material $material = Material::Solid,
        public ?TextureReference $texture = null,
    ) {
    }

    /**
     * The contrast ratio of the ink on the faces.
     */
    public function contrast(): float
    {
        return $this->face->contrastWith($this->ink);
    }

    /**
     * Whether the numbers reach the contrast given, `Color::AA` or `Color::AAA`.
     */
    public function reaches(float $ratio): bool
    {
        return $this->contrast() >= $ratio;
    }

    /**
     * @return array{face: string, ink: string, material: string, texture?: string}
     */
    public function toArray(): array
    {
        $look = ['face' => $this->face->hex, 'ink' => $this->ink->hex, 'material' => $this->material->value];

        if ($this->texture instanceof TextureReference) {
            $look['texture'] = $this->texture->key;
        }

        return $look;
    }
}
