<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\Unlock;

#[CoversClass(Unlock::class)]
#[CoversClass(InvalidScenario::class)]
final class UnlockTest extends TestCase
{
    public function testAnOpenPuzzleOpensWithNothingSolved(): void
    {
        $unlock = Unlock::open();

        self::assertSame([], $unlock->requires);
        self::assertSame(0, $unlock->minimum);
        self::assertSame(0, $unlock->afterSolves);
        self::assertFalse($unlock->manual);
        self::assertTrue($unlock->opensWith([]));
    }

    public function testAPuzzleOpensOnceAllItRequiresAreSolved(): void
    {
        $unlock = Unlock::after(['clock', 'painting']);

        self::assertSame(['clock', 'painting'], $unlock->requires);
        self::assertSame(2, $unlock->minimum);
        self::assertFalse($unlock->opensWith([]));
        self::assertFalse($unlock->opensWith(['clock' => true, 'other' => true]));
        self::assertTrue($unlock->opensWith(['painting' => true, 'clock' => true]));
    }

    public function testAPuzzleOpensOnceEnoughOfThoseItRequiresAreSolved(): void
    {
        $unlock = Unlock::after(['a', 'b', 'c'], 2);

        self::assertSame(2, $unlock->minimum);
        self::assertFalse($unlock->opensWith(['a' => true, 'x' => true, 'y' => true]));
        self::assertTrue($unlock->opensWith(['a' => true, 'c' => true]));
    }

    public function testAPuzzleOpensAfterEnoughSolvesOfAnyPuzzle(): void
    {
        $unlock = Unlock::afterSolves(2);

        self::assertSame(2, $unlock->afterSolves);
        self::assertFalse($unlock->opensWith(['a' => true]));
        self::assertTrue($unlock->opensWith(['a' => true, 'b' => true]));
    }

    public function testBothConditionsMustHold(): void
    {
        $unlock = new Unlock(['a'], null, 2);

        self::assertFalse($unlock->opensWith(['a' => true]));
        self::assertFalse($unlock->opensWith(['b' => true, 'c' => true]));
        self::assertTrue($unlock->opensWith(['a' => true, 'c' => true]));
    }

    public function testAPuzzleTheHostOpensNeverOpensByItself(): void
    {
        $unlock = Unlock::manual();

        self::assertTrue($unlock->manual);
        self::assertFalse($unlock->opensWith(['a' => true]));
    }

    public function testFiftyRequiresAreTaken(): void
    {
        $requires = array_map(static fn (int $index): string => 'p'.$index, range(1, 50));

        self::assertCount(50, Unlock::after($requires)->requires);
    }

    public function testFiftyOneRequiresAreRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A puzzle requires 50 others at most.');

        Unlock::after(array_map(static fn (int $index): string => 'p'.$index, range(1, 51)));
    }

    public function testAPuzzleRequiredTwiceIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A puzzle requires each other puzzle once.');

        Unlock::after(['a', 'a']);
    }

    public function testNoneOfThoseRequiredIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzles to solve among those required are 1 to 2.');

        Unlock::after(['a', 'b'], 0);
    }

    public function testMoreThanThoseRequiredIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzles to solve among those required are 1 to 2.');

        Unlock::after(['a', 'b'], 3);
    }

    public function testAMinimumWithoutRequiresIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzles to solve among those required are 1 to 0.');

        new Unlock([], 1);
    }

    public function testANegativeMinimumWithoutRequiresIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);

        new Unlock([], -1);
    }

    public function testANegativeCountOfSolvesIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzles to solve first are 0 or more.');

        Unlock::afterSolves(-1);
    }

    public function testAPuzzleTheHostOpensRequiresNothing(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A puzzle the host opens has no other condition.');

        new Unlock(['a'], manual: true);
    }

    public function testAPuzzleTheHostOpensWaitsForNoSolve(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A puzzle the host opens has no other condition.');

        new Unlock(afterSolves: 1, manual: true);
    }
}
