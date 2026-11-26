<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\MiniGames\ScoreOrder;
use Tilttek\MiniGames\Scoring;

#[CoversClass(ScoreOrder::class)]
#[CoversClass(Scoring::class)]
final class ScoreOrderTest extends TestCase
{
    public function testAHigherScoreIsBetterWhenHigherIsBetter(): void
    {
        self::assertTrue(ScoreOrder::HigherIsBetter->isBetter(8, 7));
        self::assertFalse(ScoreOrder::HigherIsBetter->isBetter(7, 8));
        self::assertFalse(ScoreOrder::HigherIsBetter->isBetter(7, 7));
    }

    public function testALowerScoreIsBetterWhenLowerIsBetter(): void
    {
        self::assertTrue(ScoreOrder::LowerIsBetter->isBetter(3, 4));
        self::assertFalse(ScoreOrder::LowerIsBetter->isBetter(4, 3));
        self::assertFalse(ScoreOrder::LowerIsBetter->isBetter(4, 4));
    }

    public function testTheFirstScoreIsARecordAndAnEqualScoreIsNot(): void
    {
        self::assertTrue(ScoreOrder::LowerIsBetter->isNewRecord(9, null));
        self::assertTrue(ScoreOrder::LowerIsBetter->isNewRecord(3, 4));
        self::assertFalse(ScoreOrder::LowerIsBetter->isNewRecord(4, 4));
        self::assertFalse(ScoreOrder::LowerIsBetter->isNewRecord(5, 4));
        self::assertTrue(ScoreOrder::HigherIsBetter->isNewRecord(5, 4));
        self::assertFalse(ScoreOrder::HigherIsBetter->isNewRecord(4, 4));
    }

    public function testTheBestOfSomeScores(): void
    {
        self::assertNull(ScoreOrder::HigherIsBetter->best([]));
        self::assertSame(9, ScoreOrder::HigherIsBetter->best([3, 9, 9, 1]));
        self::assertSame(1, ScoreOrder::LowerIsBetter->best([3, 9, 1, 1]));
        self::assertSame(-2, ScoreOrder::LowerIsBetter->best([-2]));
    }

    public function testTheScoringNamesItsStatistic(): void
    {
        self::assertSame('score', new Scoring(ScoreOrder::HigherIsBetter)->statistic);
        self::assertSame('seconds', new Scoring(ScoreOrder::LowerIsBetter, 'seconds')->statistic);
        self::assertSame(ScoreOrder::LowerIsBetter, new Scoring(ScoreOrder::LowerIsBetter)->order);
        self::assertSame('higher', ScoreOrder::HigherIsBetter->value);
        self::assertSame('lower', ScoreOrder::LowerIsBetter->value);
    }
}
