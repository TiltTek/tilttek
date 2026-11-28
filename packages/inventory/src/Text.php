<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use Tilttek\Inventory\Exception\InvalidInventory;

/**
 * The checks of the texts, keys and numbers the library keeps.
 *
 * @internal
 */
final class Text
{
    /** A key: 1 to 96 lowercase letters, digits, `_`, `-`, or single dots between them. */
    private const string KEY = '/^(?=.{1,96}\z)[a-z0-9_-]+(?:\.[a-z0-9_-]+)*\z/';

    /**
     * @throws InvalidInventory
     */
    public static function key(string $key, string $what): string
    {
        if (1 !== preg_match(self::KEY, $key)) {
            throw InvalidInventory::because(\sprintf('The key of %s must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "%s" is not.', $what, $key));
        }

        return $key;
    }

    /**
     * A name: 1 to `$max` characters of UTF-8 without control characters nor surrounding spaces.
     *
     * @throws InvalidInventory
     */
    public static function name(string $name, string $what, int $max): string
    {
        if (1 !== preg_match('/^\P{Cc}{1,'.$max.'}\z/u', $name) || trim($name) !== $name) {
            throw InvalidInventory::because(\sprintf('%s is 1 to %d characters of UTF-8 without control characters nor surrounding spaces.', $what, $max));
        }

        return $name;
    }

    /**
     * A whole number from `$min` to `$max`.
     *
     * @throws InvalidInventory
     */
    public static function between(int $value, int $min, int $max, string $what): int
    {
        if ($value < $min || $value > $max) {
            throw InvalidInventory::because(\sprintf('%s is %d to %d; %d is not.', $what, $min, $max, $value));
        }

        return $value;
    }
}
