<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * How a member's dice look: the name of the set, its colour, its material, an image, the ink its numbers are written in
 * and a texture. The appearance never changes the randomness: it only dresses the dice the server rolled.
 *
 * The texture is a key the application resolves to its own picture, never an address: 1 to 128 characters among
 * `a-z`, `0-9`, `.`, `_`, `/` and `-`, starting with a letter or a digit, without `..`, `//` or a final `/`.
 */
final readonly class DieAppearance
{
    private const string COLOUR = '/^#[0-9a-fA-F]{6}$/D';

    private const string TEXTURE = '~^[a-z0-9][a-z0-9._/-]{0,127}$~D';

    public function __construct(
        public string $name,
        public ?string $color = null,
        public ?string $material = null,
        public ?string $image = null,
        public ?string $ink = null,
        public ?string $texture = null,
    ) {
        if ('' === trim($name)) {
            throw InvalidDefinition::because('The appearance of a die has a name.');
        }

        if (null !== $color && 1 !== preg_match(self::COLOUR, $color)) {
            throw InvalidDefinition::because(\sprintf('The colour of a die is written #rrggbb; "%s" is not.', $color));
        }

        if (null !== $ink && 1 !== preg_match(self::COLOUR, $ink)) {
            throw InvalidDefinition::because(\sprintf('The ink of a die is written #rrggbb; "%s" is not.', $ink));
        }

        if (null !== $texture && !$this->isTexture($texture)) {
            throw InvalidDefinition::because(\sprintf('The texture of a die is a key of 1 to 128 characters among a-z, 0-9, ".", "_", "/" and "-", starting with a letter or a digit, without "..", "//" or a final "/"; "%s" is not.', $texture));
        }
    }

    /**
     * The ink the numbers are written in: the one given, or, on a die with a colour, black or white, whichever reads
     * better on it — at least 4.5:1, the contrast WCAG asks of text —; none on a die without a colour, whose ink the
     * stylesheet gives.
     */
    public function readableInk(): ?string
    {
        return $this->ink ?? (null === $this->color ? null : Contrast::readableInk($this->color));
    }

    /**
     * The appearance as a template or a JSON message shows it, the ink made readable.
     *
     * @return array{name: string, color: ?string, ink: ?string, texture: ?string, material: ?string, image: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
            'ink' => $this->readableInk(),
            'texture' => $this->texture,
            'material' => $this->material,
            'image' => $this->image,
        ];
    }

    private function isTexture(string $texture): bool
    {
        return 1 === preg_match(self::TEXTURE, $texture)
            && !str_contains($texture, '..')
            && !str_contains($texture, '//')
            && !str_ends_with($texture, '/');
    }
}
