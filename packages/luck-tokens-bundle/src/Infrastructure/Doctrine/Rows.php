<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Doctrine;

use Tilttek\LuckTokens\Exception\InvalidTokenRule;

/**
 * The values of a row as the drivers give them: an integer, or a text of digits for a driver that reads every column
 * as text; a text.
 *
 * @internal
 */
final class Rows
{
    private function __construct()
    {
    }

    public static function integer(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d{1,18}$/D', $value)) {
            return (int) $value;
        }

        throw InvalidTokenRule::because('A column of the tokens holds an integer.');
    }

    public static function text(mixed $value): string
    {
        return \is_string($value) ? $value : throw InvalidTokenRule::because('A column of the tokens holds a text.');
    }
}
