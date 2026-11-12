<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Integration;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Chance;
use Tilttek\DiceProbability\Event\AllDifferent;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exception\CannotCompute;
use Tilttek\DiceProbability\Probabilities;

/**
 * Short expressions that cost the most, under the default settings: each answers, exactly, by an estimate or by
 * CannotCompute, within a few seconds of processor time.
 */
#[CoversClass(Probabilities::class)]
final class LoadTest extends TestCase
{
    /** The processor time an expression may take, with room for a slow or busy machine. */
    private const float SECONDS = 4.0;

    #[DataProvider('expressions')]
    public function testADistributionIsBounded(string $expression): void
    {
        $pool = new DiceRoller()->parse($expression);

        $this->assertBounded(static fn (): Fraction => new Probabilities()->distribution($pool)->mean());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expressions(): iterable
    {
        yield 'thousand-sided dice' => ['10d1000'];
        yield 'a hundred exploding dice' => ['100d6!'];
        yield 'a hundred compounding dice' => ['100d6!!'];
        yield 'a hundred penetrating dice' => ['100d6!p'];
        yield 'a hundred exploding twenty-sided dice' => ['100d20!'];
        yield 'a hundred thousand-sided dice' => ['100d1000'];
        yield 'a selection among a hundred dice' => ['100d6kh50'];
        yield 'exploding dice kept' => ['10d10!kh3'];
        yield 'count and sides rolled first' => ['(1d10)d(1d100)'];
    }

    public function testAChanceIsBounded(): void
    {
        foreach (['100d6', '100d1000', '100d6r1'] as $expression) {
            $pool = new DiceRoller()->parse($expression);

            $this->assertBounded(static fn (): Chance => new Probabilities()->chance(new AllDifferent(), $pool));
        }
    }

    private function assertBounded(Closure $work): void
    {
        $start = $this->processorTime();

        try {
            $work();
        } catch (CannotCompute $cannotCompute) {
            self::assertStringContainsString('roll limit', $cannotCompute->getMessage());
        }

        self::assertLessThan(self::SECONDS, $this->processorTime() - $start);
    }

    private function processorTime(): float
    {
        $usage = getrusage();
        self::assertIsArray($usage);
        self::assertIsInt($usage['ru_utime.tv_sec']);
        self::assertIsInt($usage['ru_utime.tv_usec']);

        return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1_000_000;
    }
}
