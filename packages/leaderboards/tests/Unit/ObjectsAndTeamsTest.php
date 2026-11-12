<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Chance;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;
use Tilttek\Leaderboards\Row;
use Tilttek\Leaderboards\Snapshot;
use Tilttek\Leaderboards\Team;
use Tilttek\Leaderboards\TeamRow;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Kit;

#[CoversClass(Board::class)]
#[CoversClass(Chance::class)]
#[CoversClass(Team::class)]
#[CoversClass(TeamRow::class)]
final class ObjectsAndTeamsTest extends TestCase
{
    public function testTheKeyOfABoardRankedByObject(): void
    {
        $board = new Board('records', Measure::Best, new Schedule(PeriodKind::Ever), ['game.scored'], perObject: true);
        $game = ResourceReference::fromString('game:catan');

        self::assertSame('records@'.substr(hash('sha256', 'game:catan'), 0, 24), $board->key($game));
        self::assertSame('games', new Board('games', Measure::Count, new Schedule(PeriodKind::Ever), ['game.played'])->key());
        self::assertFalse($board->matches(Activity::of('k1', 'member:1', 'game.scored', new DateTimeImmutable('2026-11-03 UTC'))));
        self::assertTrue($board->matches(Activity::of('k1', 'member:1', 'game.scored', new DateTimeImmutable('2026-11-03 UTC'), 'game:catan')));
    }

    public function testABoardRankedByObjectIsToldWhichOne(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "records" ranks by object: say which one.');

        new Board('records', Measure::Best, new Schedule(PeriodKind::Ever), ['game.scored'], perObject: true)->key();
    }

    public function testABoardNotRankedByObjectIsToldNone(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "games" does not rank by object.');

        new Board('games', Measure::Count, new Schedule(PeriodKind::Ever), ['game.played'])->key(ResourceReference::fromString('game:catan'));
    }

    public function testAReportedBoardIsNotRankedByObject(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "level" ranks by object: it reads the objects of the activities in the journal.');

        new Board('level', Measure::Latest, new Schedule(PeriodKind::Ever), perObject: true);
    }

    public function testALeagueIsNotRankedByObject(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "xp" has a league: it ranks by week, and not by object.');

        new Board('xp', Measure::Total, new Schedule(PeriodKind::Week), ['lesson.done'], league: new League(['bronze']), perObject: true);
    }

    public function testEachObjectHasItsRanking(): void
    {
        $kit = $this->kit();
        $catan = ResourceReference::fromString('game:catan');
        $azul = ResourceReference::fromString('game:azul');
        $kit->recordOn('k1', 'member:1', 'game.scored', '2026-11-03 10:00', 'game:catan', 80);
        $kit->recordOn('k2', 'member:2', 'game.scored', '2026-11-03 11:00', 'game:catan', 95);
        $kit->recordOn('k3', 'member:1', 'game.scored', '2026-11-03 12:00', 'game:azul', 60);
        $kit->record('k4', 'member:3', 'game.scored', '2026-11-03 12:00', 99);

        $viewer = ResourceReference::fromString('member:1');

        $standing = $kit->leaderboards->standing('records', $viewer, object: $catan);

        self::assertSame([[1, 95], [2, 80]], array_map(static fn (Row $row): array => [$row->rank, $row->value], $standing->rows));
        self::assertSame(2, $standing->own?->rank);
        self::assertSame(2, $standing->participants);
        self::assertSame(1, $kit->leaderboards->rank('records', $viewer, object: $azul)?->rank);
        self::assertSame(1, $kit->leaderboards->standing('records', object: $azul)->participants);

        $kit->clock->set('2026-12-01 00:00 UTC');
        self::assertSame(['cup', 'plain'], array_map(static fn (Snapshot $taken): string => $taken->board, $kit->leaderboards->snapshotDue()));
        $snapshot = $kit->leaderboards->snapshot('records', '2026-11', $catan);
        self::assertSame($kit->boards->get('records')->key($catan), $snapshot->board);
        self::assertSame(2, $snapshot->participants);
        self::assertSame([[1, 95]], array_map(static fn (Row $row): array => [$row->rank, $row->value], $kit->leaderboards->frozen('records', '2026-11', $viewer, 1, $catan)->rows ?? []));
        self::assertNull($kit->leaderboards->frozen('records', '2026-11', $viewer, 1, $azul));
        self::assertCount(2, $kit->leaderboards->export($viewer)['scores']);
    }

    public function testAChanceRollsTheSameForATeamAndAPeriod(): void
    {
        $chance = new Chance(2, 6);
        $rolls = [];

        for ($team = 0; $team < 200; ++$team) {
            $rolls[] = $chance->roll('secret', 'cup', '2026-11', 'team-'.$team);
        }

        self::assertSame($chance->roll('secret', 'cup', '2026-11', 'team-1'), $rolls[1]);
        self::assertSame(2, min($rolls));
        self::assertSame(12, max($rolls));
        self::assertNotSame(array_map(static fn (int $team): int => $chance->roll('another', 'cup', '2026-11', 'team-'.$team), range(0, 199)), $rolls);
        self::assertNotSame(array_map(static fn (int $team): int => $chance->roll('secret', 'cup', '2026-12', 'team-'.$team), range(0, 199)), $rolls);
        self::assertNotSame(array_map(static fn (int $team): int => $chance->roll('secret', 'mug', '2026-11', 'team-'.$team), range(0, 199)), $rolls);
        self::assertSame([1, 6], [new Chance()->dice, new Chance()->faces]);
        $most = array_map(static fn (int $team): int => new Chance(10, 100)->roll('secret', 'cup', '2026-11', 'team-'.$team), range(0, 99));
        self::assertGreaterThanOrEqual(10, min($most));
        self::assertLessThanOrEqual(1000, max($most));
        self::assertGreaterThan(100, max($most));
    }

    public function testAChanceIsRolledWithASecret(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The luck in a team score is drawn from a secret of the application; none was given.');

        new Chance()->roll('', 'cup', '2026-11', 'red');
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function refusedChances(): iterable
    {
        yield 'no die' => [0, 6, '0d6'];
        yield 'too many dice' => [11, 6, '11d6'];
        yield 'one face' => [1, 1, '1d1'];
        yield 'too many faces' => [1, 101, '1d101'];
    }

    #[DataProvider('refusedChances')]
    public function testAChanceIsRefused(int $dice, int $faces, string $roll): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A team rolls 1 to 10 dice of 2 to 100 faces; %s is not one of them.', $roll));

        new Chance($dice, $faces);
    }

    public function testTeamsAreRanked(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'quest.done', '2026-11-03 10:00', 10);
        $kit->record('k2', 'member:2', 'quest.done', '2026-11-03 10:00', 5);
        $kit->record('k3', 'member:3', 'quest.done', '2026-11-03 10:00', 15);
        $kit->record('k4', 'member:4', 'quest.done', '2026-11-03 10:00', 1);

        $teams = [new Team('red', Circle::of('member:1', 'member:2', 'member:9')), new Team('blue', Circle::of('member:3')), new Team('green', Circle::of('member:4'))];

        $rows = $kit->leaderboards->teams('plain', $teams);

        self::assertEquals([new TeamRow(1, 'blue', 1, 15, 0, 15), new TeamRow(1, 'red', 2, 15, 0, 15), new TeamRow(3, 'green', 1, 1, 0, 1)], $rows);

        $lucky = $kit->leaderboards->teams('cup', $teams);
        $chance = new Chance(2, 6);
        $key = 'cup';

        foreach ($lucky as $row) {
            self::assertSame($chance->roll('a secret of the tests', $key, '2026-11', $row->team), $row->luck);
            self::assertSame($row->score + $row->luck, $row->total);
        }

        self::assertSame(['member:1'], Circle::of('member:1')->members);
        self::assertSame([], array_filter($lucky, static fn (TeamRow $row): bool => $row->luck < 2 || $row->luck > 12));
        self::assertGreaterThanOrEqual($lucky[1]->total, $lucky[0]->total);
        self::assertGreaterThanOrEqual($lucky[2]->total, $lucky[1]->total);
    }

    public function testTeamsByObject(): void
    {
        $kit = $this->kit();
        $kit->recordOn('k1', 'member:1', 'game.scored', '2026-11-03 10:00', 'game:catan', 80);

        self::assertEquals([new TeamRow(1, 'red', 1, 80, 0, 80)], $kit->leaderboards->teams('records', [new Team('red', Circle::of('member:1'))], '2026-11', ResourceReference::fromString('game:catan')));
    }

    public function testTeamsAreNamedOnce(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A ranking of teams holds 1 to 100 teams, each named once.');

        $this->kit()->leaderboards->teams('plain', [new Team('red', Circle::of('member:1')), new Team('red', Circle::of('member:2'))]);
    }

    public function testTeamsAreSome(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A ranking of teams holds 1 to 100 teams, each named once.');

        $this->kit()->leaderboards->teams('plain', []);
    }

    public function testTeamsAreAHundredAtMost(): void
    {
        $teams = array_map(static fn (int $index): Team => new Team('t'.$index, Circle::of('member:'.$index)), range(1, 101));

        self::assertCount(100, $this->kit()->leaderboards->teams('plain', \array_slice($teams, 0, 100)));

        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A ranking of teams holds 1 to 100 teams, each named once.');

        $this->kit()->leaderboards->teams('plain', $teams);
    }

    public function testATeamHasAName(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('"Red" is not a team name');

        new Team('Red', Circle::of('member:1'));
    }

    public function testLuckNeedsTheSecret(): void
    {
        $kit = new Kit(Boards::fromArray(['cup' => ['measure' => 'total', 'verbs' => ['quest.done'], 'chance' => []]]), secret: '');

        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('none was given');

        $kit->leaderboards->teams('cup', [new Team('red', Circle::of('member:1'))]);
    }

    private function kit(): Kit
    {
        return new Kit(Boards::fromArray([
            'records' => ['measure' => 'best', 'verbs' => ['game.scored'], 'period' => 'month', 'per_object' => true],
            'plain' => ['measure' => 'total', 'verbs' => ['quest.done'], 'period' => 'month', 'ties' => 'shared'],
            'cup' => ['measure' => 'total', 'verbs' => ['quest.done'], 'period' => 'month', 'chance' => ['dice' => 2, 'faces' => 6]],
        ]));
    }
}
