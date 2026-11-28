<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Tilttek\Crafting\Exception\InvalidRecipe;

/**
 * The checks of the codes, tags, keys and quantities the library keeps.
 *
 * @internal
 */
final class Text
{
    /** A code or a tag: 1 to 96 lowercase letters, digits, `_`, `-`, or single dots between them, starting with a letter. */
    private const string CODE = '/^(?=.{1,96}\z)[a-z][a-z0-9_-]*(?:\.[a-z0-9_-]+)*\z/';

    /** A key: 1 to 191 visible ASCII characters, no space. */
    private const string KEY = '/^[\x21-\x7E]{1,191}\z/';

    /**
     * @throws InvalidRecipe
     */
    public static function code(string $code, string $what): string
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw InvalidRecipe::because(\sprintf('The code of %s must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them, starting with a letter; "%s" is not.', $what, $code));
        }

        return $code;
    }

    /**
     * @throws InvalidRecipe
     */
    public static function key(string $key): string
    {
        if (1 !== preg_match(self::KEY, $key)) {
            throw InvalidRecipe::because('The key of a craft is 1 to 191 visible ASCII characters, without spaces.');
        }

        return $key;
    }

    /**
     * @throws InvalidRecipe
     */
    public static function quantity(int $quantity, string $what, int $max): int
    {
        if ($quantity < 1 || $quantity > $max) {
            throw InvalidRecipe::because(\sprintf('The quantity of %s is 1 to %d; it is %d.', $what, $max, $quantity));
        }

        return $quantity;
    }
}
