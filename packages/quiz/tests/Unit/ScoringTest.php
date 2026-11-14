<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Grade;
use Tilttek\Quiz\Scoring;
use Tilttek\Quiz\ScoringMode;

#[CoversClass(Scoring::class)]
final class ScoringTest extends TestCase
{
    public function testAccuracyIsTheDefaultAndGivesNoBonus(): void
    {
        $scoring = new Scoring();

        self::assertSame(ScoringMode::Accuracy, $scoring->mode);
        self::assertSame(50, $scoring->bonus);
        self::assertSame(100, $scoring->points(100, Grade::right()));
        self::assertSame(0, $scoring->bonus(100, Grade::right(), 0, 20));
        self::assertSame(100, $scoring->most(100, 20));
    }

    public function testThePointsFollowTheGradeRoundedDown(): void
    {
        self::assertSame(33, Scoring::accuracy()->points(100, Grade::share(1, 3)));
        self::assertSame(0, Scoring::accuracy()->points(100, Grade::wrong()));
        self::assertSame(65, Scoring::accuracy()->points(99, Grade::share(2, 3)));
    }

    public function testTheSpeedBonusFallsInAStraightLineToNothing(): void
    {
        $scoring = Scoring::speed();

        self::assertSame(ScoringMode::Speed, $scoring->mode);
        self::assertSame(50, $scoring->bonus(100, Grade::right(), 0, 10));
        self::assertSame(50, $scoring->bonus(100, Grade::right(), -5, 10));
        self::assertSame(25, $scoring->bonus(100, Grade::right(), 5000, 10));
        self::assertSame(0, $scoring->bonus(100, Grade::right(), 9999, 10));
        self::assertSame(0, $scoring->bonus(100, Grade::right(), 10_000, 10));
        self::assertSame(0, $scoring->bonus(100, Grade::right(), 12_000, 10));
        self::assertSame(37, Scoring::speed(75)->bonus(100, Grade::right(), 5000, 10));
        self::assertSame(150, $scoring->most(100, 10));
        self::assertSame(100, $scoring->most(100, null));
    }

    public function testNoBonusForAPartialAnswerOrAnUntimedQuestion(): void
    {
        self::assertSame(0, Scoring::speed()->bonus(100, Grade::of(999), 0, 10));
        self::assertSame(0, Scoring::speed()->bonus(100, Grade::right(), 0, null));
    }

    public function testTheBonusIsBounded(): void
    {
        self::assertSame(100, Scoring::speed(100)->bonus(100, Grade::right(), 0, 10));
        self::assertSame(0, Scoring::speed(0)->bonus(100, Grade::right(), 0, 10));

        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The speed bonus is 0 to 100 percent of the points; 101 is not allowed.');
        Scoring::speed(101);
    }

    public function testANegativeBonusIsRefused(): void
    {
        $this->expectException(InvalidQuiz::class);
        Scoring::speed(-1);
    }

    public function testTheBonusIsCountedInMilliseconds(): void
    {
        $speed = Scoring::speed(100);

        self::assertSame(1, $speed->bonus(10_000, Grade::right(), 9_999, 10));
        self::assertSame(10_000, $speed->bonus(10_000, Grade::right(), -5, 10));
        self::assertSame(0, $speed->bonus(10_000, Grade::right(), 10_000, 10));
        self::assertSame(0, $speed->bonus(10_000, Grade::right(), 10_001, 10));
        self::assertSame(20_000, $speed->most(10_000, 10));
    }
}
