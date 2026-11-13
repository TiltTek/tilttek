<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

use Tilttek\Stickers\Exception\InvalidSticker;

/**
 * A sticker of the catalogue: its code, a reference to its image the application resolves — an asset path, a key of
 * its media library, an address —, the text alternative every sticker has, and its licence and attribution. A retired
 * sticker stays with those who own it and stays where it is placed, but is placed no more.
 */
final readonly class Sticker
{
    public const int MAX_IMAGE_LENGTH = 2048;

    public const int MAX_ALT_LENGTH = 250;

    public const int MAX_LICENCE_LENGTH = 100;

    public const int MAX_ATTRIBUTION_LENGTH = 500;

    public string $alt;

    public ?string $licence;

    public ?string $attribution;

    /**
     * @throws InvalidSticker when the code, the image reference, the text alternative, the licence or the attribution
     *                        is refused
     */
    public function __construct(
        public string $code,
        public string $image,
        string $alt,
        ?string $licence = null,
        ?string $attribution = null,
        public bool $retired = false,
    ) {
        Guard::code($code, 'sticker');

        if ('' === $image || \strlen($image) > self::MAX_IMAGE_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $image)) {
            throw InvalidSticker::because(\sprintf('The image of the sticker "%s" is a reference of 1 to %d bytes without whitespace or control characters.', $code, self::MAX_IMAGE_LENGTH));
        }

        if (1 === preg_match('/^(?:javascript|vbscript|data|file):/i', $image)) {
            throw InvalidSticker::because(\sprintf('The image of the sticker "%s" is a reference to an image, never a script, inline data or a local file.', $code));
        }

        $this->alt = Guard::text($alt, 'text alternative of a sticker', self::MAX_ALT_LENGTH);
        $this->licence = null === $licence ? null : Guard::text($licence, 'licence of a sticker', self::MAX_LICENCE_LENGTH);
        $this->attribution = null === $attribution ? null : Guard::text($attribution, 'attribution of a sticker', self::MAX_ATTRIBUTION_LENGTH);
    }

    public function retire(): self
    {
        return new self($this->code, $this->image, $this->alt, $this->licence, $this->attribution, true);
    }
}
