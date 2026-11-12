<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceProbability\Chance;
use Tilttek\DiceProbability\Estimate;
use Tilttek\DiceProbability\EstimateReason;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Settings;

#[CoversClass(Settings::class)]
#[CoversClass(Chance::class)]
final class SettingsTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $settings = new Settings();

        self::assertSame(10, $settings->explosionDepth);
        self::assertSame(500_000, $settings->workLimit);
        self::assertSame(20_000, $settings->samples);
        self::assertSame(0, $settings->seed);
        self::assertSame(200_000, $settings->rollLimit);
        self::assertSame(1, new Settings(1, 1, 1, -5, 1)->explosionDepth);
    }

    #[DataProvider('belowOne')]
    public function testEachBoundIsAtLeastOne(int $depth, int $work, int $samples, int $rolls, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Settings($depth, $work, $samples, rollLimit: $rolls);
    }

    /**
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function belowOne(): iterable
    {
        yield 'depth' => [0, 1, 1, 1, 'The explosion depth is at least 1; 0 is not.'];
        yield 'work' => [1, 0, 1, 1, 'The work limit is at least 1; 0 is not.'];
        yield 'samples' => [1, 1, -1, 1, 'The number of samples is at least 1; -1 is not.'];
        yield 'rolls' => [1, 1, 1, 0, 'The roll limit is at least 1; 0 is not.'];
    }

    public function testAChanceIsExactOrEstimated(): void
    {
        $exact = new Chance(Fraction::of(16, 36));
        $estimate = new Estimate(100, 3, EstimateReason::Unsupported);
        $estimated = new Chance(Fraction::of(40, 100), $estimate, Fraction::of(1, 6));

        self::assertSame('16/36', (string) $exact->probability);
        self::assertFalse($exact->isEstimated());
        self::assertNull($exact->margin());
        self::assertTrue($exact->remaining->isZero());
        self::assertTrue($estimated->isEstimated());
        self::assertSame($estimate->margin(), $estimated->margin());
        self::assertSame('1/6', (string) $estimated->remaining);
    }
}
