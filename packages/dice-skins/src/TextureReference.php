<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

use Tilttek\DiceSkins\Exception\InvalidSkin;

/**
 * The key of a texture the application keeps — `marble/veins-02`, never an address —, which the renderer resolves to
 * an image of its own. A key is 1 to 128 characters among lower-case letters, digits, `.`, `_`, `-` and `/`, starts
 * with a letter or a digit, and holds no `..` nor `//` and no `/` at its end, so that it never leaves the folder of
 * the textures.
 */
final readonly class TextureReference
{
    public const int MAX_LENGTH = 128;

    public string $key;

    /**
     * @throws InvalidSkin when the key is not one as described above
     */
    public function __construct(string $key)
    {
        if (1 !== preg_match('#^[a-z0-9][a-z0-9._/-]{0,127}$#D', $key) || str_contains($key, '..') || str_contains($key, '//') || str_ends_with($key, '/')) {
            throw InvalidSkin::because(\sprintf('A texture is a key of 1 to %d characters among a-z, 0-9, ".", "_", "-" and "/", never an address; "%s" is not.', self::MAX_LENGTH, mb_substr($key, 0, 32)));
        }

        $this->key = $key;
    }
}
