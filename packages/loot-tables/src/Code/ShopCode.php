<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Code;

use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\LootBox;

/**
 * The faces of a result as a shop writes them in a code: one character per die, in the order of the tables. The
 * digits 1 to 9 stand for themselves, then the hexadecimal goes on past F to K: `A` is 10 — a d10's highest face —,
 * `C` a d12's and `K` a d20's. A six-dice result `3 5 2 6 1 4` is `352614`; a d20 that shows 17 is `H`.
 *
 * Only a box whose dice have 20 sides or fewer has a code.
 */
final readonly class ShopCode
{
    /** The characters of the faces 1 to 20, in order. */
    public const string ALPHABET = '123456789ABCDEFGHIJK';

    /** The largest die a code writes. */
    public const int MAX_SIDES = 20;

    /**
     * Whether every die of the box has a character for each of its faces.
     */
    public static function supports(LootBox $box): bool
    {
        return max($box->dice()) <= self::MAX_SIDES;
    }

    /**
     * @throws InvalidCode when a die of the box has more than 20 sides
     */
    public static function write(LootResult $result): string
    {
        self::checkSupported($result->box);

        return implode('', array_map(static fn (int $face): string => self::ALPHABET[$face - 1], $result->faces()));
    }

    /**
     * The result a code stands for: one character per die, upper or lower case, each a face of its die.
     *
     * @throws InvalidCode when the code has not one character per die, a character is not a face, or the box has no code
     */
    public static function read(LootBox $box, string $code, DrawSource $source = DrawSource::Physical): LootResult
    {
        self::checkSupported($box);
        $dice = $box->dice();

        if (\strlen($code) !== \count($dice)) {
            throw InvalidCode::because(\sprintf('A code of the box %s has %d characters, one per die.', $box->key, \count($dice)));
        }

        $faces = [];

        foreach ($dice as $index => $sides) {
            $face = strpos(self::ALPHABET, strtoupper($code[$index]));

            if (false === $face || $face >= $sides) {
                throw InvalidCode::because(\sprintf('Character %d of the code is not a face of a d%d: %s.', $index + 1, $sides, substr(self::ALPHABET, 0, $sides)));
            }

            $faces[] = $face + 1;
        }

        return LootResult::ofFaces($box, $faces, $source);
    }

    private static function checkSupported(LootBox $box): void
    {
        if (!self::supports($box)) {
            throw InvalidCode::because(\sprintf('The box %s rolls a die of more than %d sides: it has no code.', $box->key, self::MAX_SIDES));
        }
    }
}
