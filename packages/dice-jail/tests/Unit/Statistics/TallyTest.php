<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Tests\Unit\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\DiceJail\Exception\InvalidRolls;
use Tilttek\DiceJail\Statistics\Tally;

#[CoversClass(Tally::class)]
#[CoversClass(InvalidRolls::class)]
final class TallyTest extends TestCase
{
    public function testItCountsEachFaceInAscendingOrder(): void
    {
        $tally = Tally::of([6, 1, 6, 3, 1, 6]);

        self::assertSame([1 => 2, 3 => 1, 6 => 3], $tally->counts);
        self::assertSame(6, $tally->total);
        self::assertSame(3, $tally->count(6));
        self::assertSame(0, $tally->count(2));
    }

    public function testAnEmptyTallyHoldsNothing(): void
    {
        $tally = Tally::of([]);

        self::assertSame([], $tally->counts);
        self::assertSame(0, $tally->total);
    }

    public function testItCountsEveryRollOfTheRolledDice(): void
    {
        $tally = Tally::ofRolledDice([
            new RolledDie(4),
            new RolledDie(2, rerolled: true),
            new RolledDie(9, exploded: true, rolls: [6, 3]),
        ]);

        self::assertSame([2 => 1, 3 => 1, 4 => 1, 6 => 1], $tally->counts);
    }

    public function testItTakesAtMostAMillionRolls(): void
    {
        $atLimit = Tally::of((static function (): iterable {
            for ($i = 0; $i < Tally::MAX_ROLLS; ++$i) {
                yield 1 + $i % 2;
            }
        })());
        self::assertSame(Tally::MAX_ROLLS, $atLimit->total);

        try {
            Tally::of((static function (): iterable {
                for ($i = 0; $i <= Tally::MAX_ROLLS; ++$i) {
                    yield 1;
                }
            })());
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('A tally holds at most 1000000 rolls.', $invalidRolls->getMessage());
        }
    }
}
