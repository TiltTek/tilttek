<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Predictions\Brier;
use Tilttek\Predictions\Exception\InvalidPrediction;

#[CoversClass(Brier::class)]
final class BrierTest extends TestCase
{
    /**
     * @param array<int|string, int> $percents
     */
    #[DataProvider('knownScores')]
    public function testKnownScores(array $percents, string $outcome, int $score, int $points): void
    {
        self::assertSame($score, Brier::score($percents, $outcome));
        self::assertSame($points, Brier::points(100, $score));
    }

    /**
     * @return iterable<array{array<int|string, int>, string, int, int}>
     */
    public static function knownScores(): iterable
    {
        // (1 - 1)² + (0 - 0)² = 0: a perfect forecast.
        yield 'sure and right' => [['yes' => 100, 'no' => 0], 'yes', 0, 100];
        // (0 - 1)² + (1 - 0)² = 2: full confidence in what did not happen.
        yield 'sure and wrong' => [['yes' => 100, 'no' => 0], 'no', 20_000, 0];
        // (0.5 - 1)² + (0.5 - 0)² = 0.5.
        yield 'even' => [['yes' => 50, 'no' => 50], 'no', 5_000, 75];
        // (0.7 - 1)² + (0.3 - 0)² = 0.18.
        yield 'seventy and right' => [['yes' => 70, 'no' => 30], 'yes', 1_800, 91];
        // (0.7 - 0)² + (0.3 - 1)² = 0.98.
        yield 'seventy and wrong' => [['yes' => 70, 'no' => 30], 'no', 9_800, 51];
        // (0.6 - 1)² + 0.3² + 0.1² = 0.26.
        yield 'three outcomes' => [['home' => 60, 'draw' => 30, 'away' => 10], 'home', 2_600, 87];
        // 0.6² + 0.3² + (0.1 - 1)² = 1.26.
        yield 'three outcomes, the unlikely one' => [['home' => 60, 'draw' => 30, 'away' => 10], 'away', 12_600, 37];
        // Outcomes named by digits are array keys turned to integers by PHP.
        yield 'digits' => [['1' => 80, '2' => 20], '2', 12_800, 36];
    }

    public function testPointsRoundHalfUp(): void
    {
        // 3 × (20000 - 5000) / 20000 = 2.25, and 3 × (20000 - 3334) / 20000 = 2.4999.
        self::assertSame(2, Brier::points(3, 5_000));
        self::assertSame(2, Brier::points(3, 3_334));
        // 3 × (20000 - 3333) / 20000 = 2.50005 and 1 × 10000 / 20000 = 0.5.
        self::assertSame(3, Brier::points(3, 3_333));
        self::assertSame(1, Brier::points(1, 10_000));
        self::assertSame(0, Brier::points(1, 10_001));
        self::assertSame(1_000_000, Brier::points(1_000_000, 0));
    }

    public function testAnOutcomeWithoutProbabilityIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPrediction::because('The forecast gives no probability to "draw".'));
        Brier::score(['home' => 50, 'away' => 50], 'draw');
    }
}
