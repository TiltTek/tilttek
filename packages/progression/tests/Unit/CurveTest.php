<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Curve;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Level;

#[CoversClass(Curve::class)]
#[CoversClass(Level::class)]
#[CoversClass(InvalidProgression::class)]
final class CurveTest extends TestCase
{
    public function testTheShapesOfACurve(): void
    {
        self::assertSame([0, 100, 200, 300], Curve::linear(100, 4)->thresholds);
        self::assertSame([0, 50, 200, 450], Curve::quadratic(50, 4)->thresholds);
        self::assertSame([0, 100, 250, 475, 813, 1319], Curve::exponential(100, 1.5, 6)->thresholds);
        self::assertSame([0, 300, 900], Curve::table([300, 900])->thresholds);
        self::assertSame([0, 1, 11], Curve::exponential(1, 10.0, 3)->thresholds);
        self::assertSame(Curve::DEFAULT_LEVELS, Curve::linear(10)->levels());
        self::assertSame(Curve::DEFAULT_LEVELS, Curve::quadratic(10)->levels());
        self::assertSame(Curve::DEFAULT_LEVELS, Curve::exponential(10, 1.01)->levels());
        self::assertSame(Curve::MAX_LEVELS, Curve::linear(1, Curve::MAX_LEVELS)->levels());
        self::assertSame(2, Curve::linear(1, 2)->levels());
        self::assertSame([0, Curve::MAX_EXPERIENCE], Curve::linear(Curve::MAX_EXPERIENCE, 2)->thresholds);
        self::assertSame(Curve::MAX_LEVELS, new Curve(range(1, Curve::MAX_LEVELS - 1))->levels());
        self::assertSame([0, Curve::MAX_EXPERIENCE], new Curve([Curve::MAX_EXPERIENCE])->thresholds);
    }

    public function testTheExperienceOfALevel(): void
    {
        $curve = Curve::linear(100, 4);

        self::assertSame(0, $curve->experienceFor(1));
        self::assertSame(300, $curve->experienceFor(4));

        $this->expectExceptionObject(InvalidProgression::because('The curve has levels 1 to 4; 5 is not one of them.'));
        $curve->experienceFor(5);
    }

    public function testNoLevelZero(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The curve has levels 1 to 4; 0 is not one of them.'));
        Curve::linear(100, 4)->experienceFor(0);
    }

    public function testWhereExperienceStands(): void
    {
        $curve = Curve::linear(100, 4);

        self::assertEquals(new Level(1, 0, 0, 100), $curve->level(0));
        self::assertEquals(new Level(1, 99, 0, 100), $curve->level(99));
        self::assertEquals(new Level(2, 100, 100, 200), $curve->level(100));
        self::assertEquals(new Level(4, 300, 300, null), $curve->level(300));
        self::assertEquals(new Level(4, 1000, 300, null), $curve->level(1000));
    }

    public function testALevel(): void
    {
        $level = new Level(2, 133, 100, 200);

        self::assertFalse($level->isLast());
        self::assertSame(33, $level->gained());
        self::assertSame(100, $level->span());
        self::assertSame(67, $level->missing());
        self::assertSame(33, $level->percent());
        self::assertSame(66, new Level(2, 300, 100, 400)->percent());

        $last = new Level(4, 350, 300, null);

        self::assertTrue($last->isLast());
        self::assertSame(50, $last->gained());
        self::assertSame(0, $last->span());
        self::assertSame(0, $last->missing());
        self::assertSame(100, $last->percent());
    }

    /**
     * @return iterable<string, array{callable(): Curve, string}>
     */
    public static function refusedCurves(): iterable
    {
        yield 'one level' => [static fn (): Curve => Curve::linear(10, 1), 'A curve has 2 to 1000 levels; 1 is not a number of levels.'];
        yield 'too many levels' => [static fn (): Curve => Curve::linear(10, 1001), 'A curve has 2 to 1000 levels; 1001 is not a number of levels.'];
        yield 'no step' => [static fn (): Curve => Curve::linear(0, 3), 'Level 2 of the curve would start at 0 experience; a level starts at 1 to 1000000000000000.'];
        yield 'too much experience' => [static fn (): Curve => Curve::linear(Curve::MAX_EXPERIENCE, 3), 'Level 3 of the curve would start at 2.0E+15 experience; a level starts at 1 to 1000000000000000.'];
        yield 'a factor of one' => [static fn (): Curve => Curve::exponential(10, 1.0), 'The factor of an exponential curve is above 1 and at most 10; 1 is not one.'];
        yield 'a factor above ten' => [static fn (): Curve => Curve::exponential(10, 10.5), 'The factor of an exponential curve is above 1 and at most 10; 10.5 is not one.'];
        yield 'a threshold repeated' => [static fn (): Curve => Curve::table([300, 300]), 'Each level of a curve starts higher than the one before, at most at 1000000000000000; 300 follows 300.'];
        yield 'a threshold at zero' => [static fn (): Curve => Curve::table([0]), 'Each level of a curve starts higher than the one before, at most at 1000000000000000; 0 follows 0.'];
        yield 'a threshold too high' => [static fn (): Curve => Curve::table([Curve::MAX_EXPERIENCE + 1]), 'Each level of a curve starts higher than the one before, at most at 1000000000000000; 1000000000000001 follows 0.'];
        yield 'an empty table' => [static fn (): Curve => Curve::table([]), 'A curve has at least 2 levels; this one has 1.'];
        yield 'a table too long' => [static fn (): Curve => Curve::table(range(1, Curve::MAX_LEVELS)), 'A curve has at most 1000 levels; this one has 1001.'];
    }

    /**
     * @param callable(): Curve $curve
     */
    #[DataProvider('refusedCurves')]
    public function testARefusedCurve(callable $curve, string $message): void
    {
        $this->expectExceptionObject(InvalidProgression::because($message));
        $curve();
    }
}
