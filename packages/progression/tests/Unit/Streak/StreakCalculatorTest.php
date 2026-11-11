<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Streak;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Streak\Grace;
use Tilttek\Progression\Streak\StreakCalculator;
use Tilttek\Progression\Streak\StreakStatus;

#[CoversClass(StreakCalculator::class)]
#[CoversClass(StreakStatus::class)]
final class StreakCalculatorTest extends TestCase
{
    /**
     * @return iterable<string, array{list<int>, list<int>, ?Grace, int, array{int, int, ?int, ?int, int}}>
     */
    public static function runs(): iterable
    {
        yield 'nothing yet' => [[], [], null, 5, [0, 0, null, null, 0]];
        yield 'nothing yet, with a grace' => [[], [], new Grace(2, 7), 5, [0, 0, null, null, 2]];
        yield 'a single day, today' => [[3], [], null, 3, [1, 1, 3, 3, 0]];
        yield 'three days, today included' => [[1, 2, 3], [], null, 3, [3, 3, 1, 3, 0]];
        yield 'three days, today not over' => [[1, 2, 3], [], null, 4, [3, 3, 1, 3, 0]];
        yield 'a day missed' => [[1, 2, 3], [], null, 5, [0, 3, null, 3, 0]];
        yield 'a day forgiven' => [[1, 2, 3], [], new Grace(1, 7), 5, [3, 3, 1, 3, 0]];
        yield 'two days missed, one forgiven' => [[1, 2, 3], [], new Grace(1, 7), 6, [0, 3, null, 3, 1]];
        yield 'a grace renewed with its window' => [[1, 3, 5, 7], [], new Grace(1, 2), 7, [4, 4, 1, 7, 0]];
        yield 'a grace back once its window passed' => [[1, 3, 5, 7], [], new Grace(1, 2), 8, [4, 4, 1, 7, 1]];
        yield 'a grace spent within its window' => [[1, 3, 5], [], new Grace(1, 3), 5, [1, 2, 5, 5, 1]];
        yield 'a frozen day' => [[1, 3], [2], null, 3, [2, 2, 1, 3, 0]];
        yield 'a day frozen before the run' => [[2, 3], [1], null, 4, [2, 2, 2, 3, 0]];
        yield 'a gap, then a new run' => [[1, 4], [], null, 4, [1, 1, 4, 4, 0]];
        yield 'the best run kept' => [[1, 2, 3, 6, 7], [], null, 7, [2, 3, 6, 7, 0]];
        yield 'a gesture ahead of now' => [[1, 5], [], null, 3, [1, 1, 5, 5, 0]];
        yield 'missed days after a gesture ahead' => [[1, 2], [], null, 1, [2, 2, 1, 2, 0]];
        // Units a trillion apart are counted without visiting the units between them.
        yield 'a gesture long before the run' => [[-1_000_000_000_000, 1, 2], [], new Grace(1, 7), 3, [2, 2, 1, 2, 1]];
        yield 'a gesture long after now' => [[1, 2, 1_000_000_000_000], [], null, 3, [3, 3, 1, 1_000_000_000_000, 0]];
        yield 'a now long after the last gesture' => [[1, 2], [5], null, 1_000_000_000_000, [0, 2, null, 2, 0]];
        yield 'a run far from the first gesture' => [[1, 1_000_000_000_000, 1_000_000_000_001], [3], new Grace(1, 7), 1_000_000_000_002, [2, 2, 1_000_000_000_000, 1_000_000_000_001, 1]];
    }

    public function testAStatusWithNothing(): void
    {
        $status = new StreakStatus('daily');

        self::assertSame([0, 0, null, null, 0], [$status->current, $status->best, $status->start, $status->lastActive, $status->graceLeft]);
        self::assertFalse($status->isAlive());
    }

    /**
     * @param list<int>                        $active
     * @param list<int>                        $frozen
     * @param array{int, int, ?int, ?int, int} $expected
     */
    #[DataProvider('runs')]
    public function testARun(array $active, array $frozen, ?Grace $grace, int $now, array $expected): void
    {
        $status = StreakCalculator::status('daily', $active, $frozen, $grace, $now);

        self::assertSame('daily', $status->streak);
        self::assertSame($expected, [$status->current, $status->best, $status->start, $status->lastActive, $status->graceLeft]);
        self::assertSame(0 < $expected[0], $status->isAlive());
    }
}
