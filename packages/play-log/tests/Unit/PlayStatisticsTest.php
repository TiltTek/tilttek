<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\PlayLog\Contract\PlayStore;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\GameStatistics;
use Tilttek\PlayLog\Internal\Tally;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayerStatistics;
use Tilttek\PlayLog\PlayQuery;
use Tilttek\PlayLog\PlayStatistics;
use Tilttek\PlayLog\Result;
use Tilttek\PlayLog\Seat;
use Tilttek\PlayLog\Statistics;
use Tilttek\PlayLog\Store\InMemoryPlayStore;

#[CoversClass(PlayStatistics::class)]
#[CoversClass(Statistics::class)]
#[CoversClass(GameStatistics::class)]
#[CoversClass(PlayerStatistics::class)]
#[CoversClass(Tally::class)]
final class PlayStatisticsTest extends TestCase
{
    private PlayStatistics $statistics;

    #[Override]
    protected function setUp(): void
    {
        $store = new InMemoryPlayStore();
        $this->statistics = new PlayStatistics($store);
        $plays = [
            new Play('p1', 'tide', 'ana', [$this->linked('ana', 10, true), $this->linked('bo', 7), new Seat('cy', 50)], Result::Competitive, $this->at('2027-01-10'), 60, 'club'),
            new Play('p2', 'tide', 'bo', [$this->linked('bo', 12, true), $this->linked('ana', 9)], Result::Competitive, $this->at('2027-01-20'), 45, 'club'),
            new Play('p3', 'ebb', 'ana', [$this->linked('ana'), $this->linked('dee'), new Seat()], Result::CooperativeWin, $this->at('2027-01-05'), null, 'club'),
            new Play('p4', 'ebb', 'ana', [$this->linked('ana', 3), $this->linked('bo', 2)], Result::Unfinished, null, 30),
            new Play('p5', 'reef', 'dee', [$this->linked('dee', 4), $this->linked('ana', 2)], Result::CooperativeLoss, $this->at('2027-02-01'), 20, 'club'),
            new Play('p6', 'tide', 'ana', [$this->linked('ana', 8, true)], Result::Competitive, $this->at('2027-01-01')),
        ];

        foreach ($plays as $play) {
            $store->save($play);
        }
    }

    public function testAMemberSeesWhatTheirPlaysAddUpTo(): void
    {
        $statistics = $this->statistics->ofMember('ana');

        self::assertSame([6, 3, 1, 155, 4, false], [$statistics->plays, $statistics->wins, $statistics->unfinished, $statistics->minutes, $statistics->timedPlays, $statistics->truncated]);
        self::assertSame(38, $statistics->averageMinutes());
        self::assertEquals([
            new GameStatistics('tide', 3, 2, 105, $this->at('2027-01-20'), 10),
            new GameStatistics('ebb', 2, 1, 30, $this->at('2027-01-05'), 3),
            new GameStatistics('reef', 1, 0, 20, $this->at('2027-02-01'), 2),
        ], $statistics->games);
        self::assertEquals([
            new PlayerStatistics('bo', 3, 1),
            new PlayerStatistics('dee', 2, 1),
        ], $statistics->players);
        self::assertSame(2, $statistics->hIndex);
        self::assertSame(3, $statistics->game('tide')?->plays);
        self::assertNull($statistics->game('unknown'));
    }

    public function testAGroupSeesItsMembers(): void
    {
        $statistics = $this->statistics->ofGroup('club');

        self::assertSame([4, null, 0, 125, 3], [$statistics->plays, $statistics->wins, $statistics->unfinished, $statistics->minutes, $statistics->timedPlays]);
        self::assertEquals([
            new GameStatistics('tide', 2, null, 105, $this->at('2027-01-20'), 12),
            new GameStatistics('ebb', 1, null, 0, $this->at('2027-01-05'), null),
            new GameStatistics('reef', 1, null, 20, $this->at('2027-02-01'), 4),
        ], $statistics->games);
        self::assertEquals([
            new PlayerStatistics('ana', 4, 2),
            new PlayerStatistics('bo', 2, 1),
            new PlayerStatistics('dee', 2, 1),
        ], $statistics->players);
        self::assertSame(1, $statistics->hIndex);
    }

    public function testAPeriodNarrowsTheStatistics(): void
    {
        $statistics = $this->statistics->ofMember('ana', $this->at('2027-01-05'), $this->at('2027-01-20'));

        self::assertSame([2, 2, 60], [$statistics->plays, $statistics->wins, $statistics->minutes]);
        self::assertSame(['ebb', 'tide'], array_map(static fn (GameStatistics $game): string => $game->game, $statistics->games));
        self::assertSame(2, $this->statistics->ofGroup('club', $this->at('2027-01-20'))->plays);
        self::assertSame(0, $this->statistics->ofGroup('club', null, $this->at('2027-01-05'))->plays);
    }

    public function testNoPlayAddsUpToNothing(): void
    {
        $statistics = $this->statistics->ofMember('nobody');

        self::assertSame([0, 0, 0, 0, 0, [], [], 0, false, null], [$statistics->plays, $statistics->wins, $statistics->unfinished, $statistics->minutes, $statistics->timedPlays, $statistics->games, $statistics->players, $statistics->hIndex, $statistics->truncated, $statistics->averageMinutes()]);
    }

    public function testAPendingSeatCountsForNoOne(): void
    {
        self::assertSame(0, $this->statistics->ofMember('cy')->plays);
        self::assertEquals([new PlayerStatistics('ana', 3, 1)], $this->statistics->ofMember('bo')->players);
    }

    public function testTheHighestScoreIsTheHighest(): void
    {
        $store = new InMemoryPlayStore();

        foreach ([[5, 'a'], [null, 'b'], [9, 'c'], [-2, 'd'], [9, 'e']] as [$score, $id]) {
            $store->save(new Play($id, 'tide', 'ana', [$this->linked('ana', $score)], playedAt: $this->at('2027-01-01')));
        }

        self::assertSame(9, new PlayStatistics($store)->ofMember('ana')->game('tide')?->highestScore);

        $negative = new InMemoryPlayStore();
        $negative->save(new Play('a', 'tide', 'ana', [$this->linked('ana', -5)]));
        $negative->save(new Play('b', 'tide', 'ana', [$this->linked('ana', -2)]));

        self::assertSame(-2, new PlayStatistics($negative)->ofMember('ana')->game('tide')?->highestScore);
    }

    public function testTheLastPlayedIsTheLatest(): void
    {
        $store = new InMemoryPlayStore();

        foreach (['a' => '2027-01-03', 'b' => null, 'c' => '2027-01-09', 'd' => '2027-01-01'] as $id => $date) {
            $store->save(new Play($id, 'tide', 'ana', [$this->linked('ana')], playedAt: null === $date ? null : $this->at($date)));
        }

        self::assertEquals($this->at('2027-01-09'), new PlayStatistics($store)->ofMember('ana')->game('tide')?->lastPlayed);
    }

    public function testTheGamesAndPlayersTieByIdentifier(): void
    {
        $store = new InMemoryPlayStore();
        $store->save(new Play('a', 'zeta', 'ana', [$this->linked('ana'), $this->linked('zed')]));
        $store->save(new Play('b', 'alpha', 'ana', [$this->linked('ana'), $this->linked('amy')]));

        $statistics = new PlayStatistics($store)->ofMember('ana');

        self::assertSame(['alpha', 'zeta'], array_map(static fn (GameStatistics $game): string => $game->game, $statistics->games));
        self::assertSame(['amy', 'zed'], array_map(static fn (PlayerStatistics $player): string => $player->member, $statistics->players));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function gameOrders(): iterable
    {
        yield 'as sorted' => [['bravo', 'bravo', 'alpha', 'charlie', 'delta']];
        yield 'reversed' => [['delta', 'charlie', 'alpha', 'bravo', 'bravo']];
        yield 'shuffled' => [['charlie', 'bravo', 'delta', 'alpha', 'bravo']];
        yield 'shuffled again' => [['alpha', 'delta', 'bravo', 'bravo', 'charlie']];
    }

    /**
     * @param list<string> $games the game of each play, the latest play first
     */
    #[DataProvider('gameOrders')]
    public function testTheGamesComeByPlaysThenIdentifier(array $games): void
    {
        $store = new InMemoryPlayStore();

        foreach ($games as $index => $game) {
            $store->save(new Play('p'.$index, $game, 'ana', [$this->linked('ana')], playedAt: $this->at('2027-01-'.(20 - $index))));
        }

        self::assertSame(['bravo', 'alpha', 'charlie', 'delta'], array_map(static fn (GameStatistics $game): string => $game->game, new PlayStatistics($store)->ofMember('ana')->games));
    }

    public function testTheHIndexCountsGamesPlayedAsOftenAsTheirRank(): void
    {
        $store = new InMemoryPlayStore();
        $n = 0;

        foreach (['a' => 5, 'b' => 3, 'c' => 3, 'd' => 1, 'e' => 3] as $game => $plays) {
            for ($i = 0; $i < $plays; ++$i) {
                $store->save(new Play('p'.++$n, $game, 'ana', [$this->linked('ana')]));
            }
        }

        self::assertSame(3, new PlayStatistics($store)->ofMember('ana')->hIndex);
    }

    public function testThePlayersAreBounded(): void
    {
        $store = new InMemoryPlayStore();

        for ($i = 0; $i < 60; ++$i) {
            $store->save(new Play('p'.$i, 'tide', 'ana', [$this->linked('ana'), $this->linked(\sprintf('m%02d', $i))]));
        }

        $players = new PlayStatistics($store)->ofMember('ana')->players;

        self::assertCount(50, $players);
        self::assertSame(['m00', 'm49'], [$players[0]->member, $players[49]->member]);
    }

    public function testAtMostTenThousandPlaysAreRead(): void
    {
        $store = new class implements PlayStore {
            public int $asked = 0;

            #[Override]
            public function play(string $id): ?Play
            {
                return null;
            }

            #[Override]
            public function save(Play $play): Play
            {
                return $play;
            }

            #[Override]
            public function delete(Play $play): void
            {
            }

            #[Override]
            public function plays(PlayQuery $query, int $limit): iterable
            {
                $this->asked = $limit;
                $play = new Play('p', 'tide', 'ana', [new Seat('ana', confirmed: true)], minutes: 1);

                for ($i = 0; $i < $limit; ++$i) {
                    yield $play;
                }
            }
        };

        $statistics = new PlayStatistics($store)->ofMember('ana');

        self::assertSame([10_000, 10_000, true, 10_001], [$statistics->plays, $statistics->minutes, $statistics->truncated, $store->asked]);
    }

    public function testExactlyTenThousandPlaysAreNotTruncated(): void
    {
        $store = new class implements PlayStore {
            #[Override]
            public function play(string $id): ?Play
            {
                return null;
            }

            #[Override]
            public function save(Play $play): Play
            {
                return $play;
            }

            #[Override]
            public function delete(Play $play): void
            {
            }

            #[Override]
            public function plays(PlayQuery $query, int $limit): iterable
            {
                $play = new Play('p', 'tide', 'ana', [new Seat('ana', confirmed: true)]);

                for ($i = 0; $i < 10_000; ++$i) {
                    yield $play;
                }
            }
        };

        self::assertFalse(new PlayStatistics($store)->ofMember('ana')->truncated);
    }

    public function testTheSubjectIsAnIdentifier(): void
    {
        $this->expectException(InvalidPlay::class);

        $this->statistics->ofGroup('a club');
    }

    private function linked(string $member, ?int $score = null, bool $winner = false): Seat
    {
        return new Seat($member, $score, $winner, true);
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 20:00 UTC');
    }
}
