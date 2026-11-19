<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Leaderboard;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\EscapeRoom\Standing;

#[CoversClass(Leaderboard::class)]
#[CoversClass(Standing::class)]
#[CoversClass(RoomResult::class)]
#[CoversClass(InvalidRoom::class)]
final class LeaderboardTest extends TestCase
{
    public function testOnlyTheTeamsThatAgreedAreRankedByTimeHintsAndEscape(): void
    {
        $results = [
            self::escape('slow', 1_900, 0, '20:40'),
            new RoomResult('crypt', ResourceReference::of('team', 'hidden'), null, 900, 900, 0, 0, 0, new DateTimeImmutable('2026-11-13 20:10 UTC'), false),
            self::escape('fast', 1_200, 2, '20:30'),
            self::escape('fewer-hints', 1_200, 1, '20:35'),
            self::escape('tied-later', 1_200, 2, '20:31'),
            self::escape('other-room', 100, 0, '20:01', 'cell'),
        ];

        $standings = Leaderboard::rank('crypt', $results);

        self::assertSame(
            [[1, 'fewer-hints'], [2, 'fast'], [2, 'tied-later'], [4, 'slow']],
            array_map(static fn (Standing $standing): array => [$standing->position, $standing->result->team->id], $standings),
        );
    }

    public function testTheTiesAreBrokenByWhoEscapedFirstInTheOrder(): void
    {
        $standings = Leaderboard::rank('crypt', [self::escape('b', 600, 0, '20:30:00.000002'), self::escape('a', 600, 0, '20:30:00.000001')]);

        self::assertSame(['a', 'b'], array_map(static fn (Standing $standing): string => $standing->result->team->id, $standings));
        self::assertSame([1, 1], array_map(static fn (Standing $standing): int => $standing->position, $standings));
    }

    public function testTheLimitCutsTheList(): void
    {
        $results = array_map(static fn (int $index): RoomResult => self::escape('t'.$index, 100 + $index, 0, '20:00'), range(1, 1_001));

        self::assertCount(10, Leaderboard::rank('crypt', $results));
        self::assertCount(1, Leaderboard::rank('crypt', $results, 1));
        self::assertCount(1_000, Leaderboard::rank('crypt', $results, 1_000));
        self::assertSame(1_000, Leaderboard::rank('crypt', $results, 1_000)[999]->position);
    }

    public function testNoResultGivesAnEmptyLeaderboard(): void
    {
        self::assertSame([], Leaderboard::rank('crypt', []));
    }

    public function testANoLimitIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A leaderboard shows 1 to 1000 places.');

        Leaderboard::rank('crypt', [], 0);
    }

    public function testALargerLimitIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);

        Leaderboard::rank('crypt', [], 1_001);
    }

    private static function escape(string $team, int $final, int $hints, string $at, string $room = 'crypt'): RoomResult
    {
        return new RoomResult($room, ResourceReference::of('team', $team), 's', $final, $final, 0, $hints, 0, new DateTimeImmutable('2026-11-13 '.$at.' UTC'), true);
    }
}
