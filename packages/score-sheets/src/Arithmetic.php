<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidEntry;

/**
 * Integer sums and products that refuse to overflow rather than turn into floats.
 *
 * @internal
 */
final class Arithmetic
{
    public static function add(int $left, int $right, string $category): int
    {
        return self::checked($left + $right, $category);
    }

    public static function multiply(int $left, int $right, string $category): int
    {
        return self::checked($left * $right, $category);
    }

    private static function checked(int|float $result, string $category): int
    {
        return \is_int($result) ? $result : throw InvalidEntry::because(\sprintf('The category "%s" is too large to compute.', $category));
    }
}
