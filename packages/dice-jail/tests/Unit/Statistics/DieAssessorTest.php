<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Tests\Unit\Statistics;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceJail\Statistics\DieAssessor;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceJail\Store\InMemoryRollSource;

#[CoversClass(DieAssessor::class)]
#[CoversClass(InMemoryRollSource::class)]
final class DieAssessorTest extends TestCase
{
    public function testItJudgesTheRollsOfOneDieFromTheSource(): void
    {
        $source = new InMemoryRollSource();
        $before = new DateTimeImmutable('2026-11-01 20:00');
        $after = new DateTimeImmutable('2026-11-08 20:00');

        foreach ([1, 1, 2, 3] as $face) {
            $source->record('d6:red', $face, $before);
        }

        foreach ([6, 6] as $face) {
            $source->record('d6:red', $face, $after);
        }

        $source->record('d6:blue', 4, $after);
        $assessor = new DieAssessor($source);

        self::assertSame(6, $assessor->assess('d6:red', ExpectedFaces::uniform(6))->rolls);
        self::assertSame(2, $assessor->assess('d6:red', ExpectedFaces::uniform(6), $after)->rolls);
        self::assertSame(2, $assessor->assess('d6:red', ExpectedFaces::uniform(6), $after->modify('-1 second'))->rolls);
        self::assertSame(0, $assessor->assess('d6:red', ExpectedFaces::uniform(6), $after->modify('+1 second'))->rolls);
        self::assertSame(1, $assessor->assess('d6:blue', ExpectedFaces::uniform(6))->rolls);
        self::assertSame(0, $assessor->assess('d6:green', ExpectedFaces::uniform(6))->rolls);

        $source->reset();

        self::assertSame(0, $assessor->assess('d6:red', ExpectedFaces::uniform(6))->rolls);
    }
}
