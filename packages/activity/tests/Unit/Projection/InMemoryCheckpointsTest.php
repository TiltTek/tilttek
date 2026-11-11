<?php

declare(strict_types=1);

namespace Tilttek\Activity\Tests\Unit\Projection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Projection\InMemoryCheckpoints;

#[CoversClass(InMemoryCheckpoints::class)]
final class InMemoryCheckpointsTest extends TestCase
{
    public function testACheckpointStartsAtZeroAndMoves(): void
    {
        $checkpoints = new InMemoryCheckpoints();
        self::assertSame(0, $checkpoints->position('badges'));

        $checkpoints->save('badges', 12);

        self::assertSame(12, $checkpoints->position('badges'));
        self::assertSame(0, $checkpoints->position('points'));
    }

    public function testResetBringsEveryCheckpointBackToZero(): void
    {
        $checkpoints = new InMemoryCheckpoints();
        $checkpoints->save('badges', 12);

        $checkpoints->reset();

        self::assertSame(0, $checkpoints->position('badges'));
    }
}
