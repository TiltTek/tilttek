<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\Deck;
use Tilttek\DeckBuilder\DeckDiff;

#[CoversClass(DeckDiff::class)]
final class DeckDiffTest extends TestCase
{
    public function testTheDifferencesBetweenTwoVersionsAreListed(): void
    {
        $from = new Deck('d', 'Foxes', 'open', ['a' => 2, 'b' => 1, 'c' => 3, 'd' => 1]);
        $to = new Deck('d', 'Foxes II', 'league', ['c' => 1, 'e' => 2, 'a' => 2, 'b' => 4]);
        $diff = DeckDiff::between($from, $to);

        self::assertSame(['e' => 2], $diff->added);
        self::assertSame(['d' => 1], $diff->removed);
        self::assertSame(['c' => [3, 1], 'b' => [1, 4]], $diff->changed);
        self::assertTrue($diff->nameChanged());
        self::assertTrue($diff->formatChanged());
        self::assertFalse($diff->isEmpty());
        self::assertSame($from, $diff->from);
        self::assertSame($to, $diff->to);
        self::assertSame(['+2 e', '-1 d', 'c: 3 → 1', 'b: 1 → 4', 'Name: "Foxes" → "Foxes II"', 'Format: open → league'], $diff->lines());
    }

    public function testTwoEqualVersionsHaveNoDifference(): void
    {
        $deck = new Deck('d', 'D', 'f', ['a' => 1]);
        $diff = DeckDiff::between($deck, new Deck('d', 'D', 'f', ['a' => 1]));

        self::assertTrue($diff->isEmpty());
        self::assertSame([], $diff->lines());
        self::assertFalse($diff->nameChanged());
        self::assertFalse($diff->formatChanged());
    }

    /**
     * Each difference alone makes the diff non-empty.
     */
    public function testEachDifferenceCounts(): void
    {
        $deck = new Deck('d', 'D', 'f', ['a' => 1]);

        self::assertFalse(DeckDiff::between($deck, $deck->withCard('b'))->isEmpty());
        self::assertFalse(DeckDiff::between($deck, $deck->withoutCard('a'))->isEmpty());
        self::assertFalse(DeckDiff::between($deck, $deck->withCard('a'))->isEmpty());
        self::assertFalse(DeckDiff::between($deck, $deck->renamed('E'))->isEmpty());
        self::assertFalse(DeckDiff::between($deck, new Deck('d', 'D', 'g', ['a' => 1]))->isEmpty());
        self::assertSame(['Format: f → g'], DeckDiff::between($deck, new Deck('d', 'D', 'g', ['a' => 1]))->lines());
    }
}
