<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Activity\VerbPattern;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\LeaderboardProjection;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Tests\Unit\Fixture\CountingScoreStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Kit;

#[CoversClass(LeaderboardProjection::class)]
#[CoversClass(Contribution::class)]
final class LeaderboardProjectionTest extends TestCase
{
    public function testTheProjection(): void
    {
        $kit = $this->kit();

        self::assertSame('leaderboards', $kit->projection->name());
        self::assertSame('ranks', new LeaderboardProjection($kit->boards, $kit->scores, 'ranks')->name());
        self::assertSame(['arcade.scored', 'game.played', 'game.*'], array_map(static fn (VerbPattern $pattern): string => $pattern->value, $kit->projection->filter()->verbs->patterns));
    }

    public function testTheActivityKeepsTheScoresByPeriod(): void
    {
        $kit = $this->kit();

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00');
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 10:00', 3);
        $kit->record('k3', 'member:1', 'game.won', '2026-11-04 10:00', 2);
        $kit->record('k4', 'member:2', 'game.played', '2026-11-01 03:00');
        $kit->record('k5', 'member:1', 'arcade.scored', '2026-11-05 10:00', 120, ['ranked' => true]);
        $kit->record('k6', 'member:1', 'arcade.scored', '2026-11-06 10:00', 90, ['ranked' => true]);
        $kit->record('k7', 'member:1', 'arcade.scored', '2026-11-04 11:00', 150);

        self::assertSame([
            'games member:1 2026-11=2 2026-11-03 10:00',
            'points member:1 ever=6 2026-11-04 10:00',
            'arcade member:1 2026-w45=120 2026-11-05 10:00',
        ], [
            $this->line($kit->scores->score('games', '2026-11', $this->member('member:1'))),
            $this->line($kit->scores->score('points', 'ever', $this->member('member:1'))),
            $this->line($kit->scores->score('arcade', '2026-w45', $this->member('member:1'))),
        ]);
        // 03:00 UTC on the first of November is still October in Toronto.
        self::assertSame('games member:2 2026-10=1 2026-11-01 03:00', $this->line($kit->scores->score('games', '2026-10', $this->member('member:2'))));
        self::assertNull($kit->scores->score('level', 'ever', $this->member('member:1')));
    }

    public function testAnActivityHandedTwiceCountsOnce(): void
    {
        $kit = $this->kit();
        $entry = new Entry(1, EntryKind::Recorded, Activity::of('k1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 10:00 UTC'), quantity: 4), new DateTimeImmutable('2026-11-02 10:00 UTC'));

        $kit->projection->apply($entry);
        $kit->projection->apply($entry);

        self::assertSame(4, $kit->scores->score('points', 'ever', $this->member('member:1'))?->value);
        self::assertSame(1, $kit->scores->score('games', '2026-11', $this->member('member:1'))?->value);
    }

    public function testAnActivityOutsideTheSeasonsCountsForNone(): void
    {
        $kit = new Kit(Boards::fromArray(['summer' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'season', 'seasons' => [['code' => 's', 'start' => '2026-06-21', 'end' => '2026-09-22']]], 'ever' => ['measure' => 'count', 'verbs' => ['game.played']]]));

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00');

        self::assertSame(0, $kit->scores->participants('summer', 's'));
        self::assertSame(1, $kit->scores->score('ever', 'ever', $this->member('member:1'))?->value);
    }

    public function testACancellationCountsTheScoresAgain(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'arcade.scored', '2026-11-03 10:00', 80, ['ranked' => true]);
        $kit->record('k2', 'member:1', 'arcade.scored', '2026-11-04 10:00', 120, ['ranked' => true]);
        $kit->record('k3', 'member:1', 'game.played', '2026-11-04 10:00', 2);
        $kit->record('k4', 'member:1', 'game.played', '2026-11-05 10:00', 3);

        $kit->journal->cancel('k2');
        $kit->journal->cancel('k4');

        self::assertSame('arcade member:1 2026-w45=80 2026-11-03 10:00', $this->line($kit->scores->score('arcade', '2026-w45', $this->member('member:1'))));
        self::assertSame('points member:1 ever=2 2026-11-04 10:00', $this->line($kit->scores->score('points', 'ever', $this->member('member:1'))));
        self::assertSame('games member:1 2026-11=1 2026-11-04 10:00', $this->line($kit->scores->score('games', '2026-11', $this->member('member:1'))));

        $kit->journal->cancel('k1');
        $kit->journal->cancel('k3');

        self::assertNull($kit->scores->score('arcade', '2026-w45', $this->member('member:1')));
        self::assertNull($kit->scores->score('points', 'ever', $this->member('member:1')));
        self::assertNull($kit->scores->score('games', '2026-11', $this->member('member:1')));
    }

    public function testARebuildCountsAgainUnderTheNewBoards(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-10-30 10:00', 2);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 10:00', 3);
        $kit->record('k3', 'member:2', 'game.played', '2026-11-04 10:00', 5);
        $kit->leaderboards->report('level', $this->member('member:1'), 4, new DateTimeImmutable('2026-11-03 UTC'));

        $points = new LeaderboardProjection(Boards::fromArray(['points' => ['measure' => 'count', 'verbs' => ['game.*']]]), $kit->scores);
        $kit->projector->rebuild($points, new DateTimeImmutable('2026-11-01 UTC'));

        self::assertSame('points member:1 ever=3 2026-11-03 10:00', $this->line($kit->scores->score('points', 'ever', $this->member('member:1'))));
        self::assertSame('points member:2 ever=1 2026-11-04 10:00', $this->line($kit->scores->score('points', 'ever', $this->member('member:2'))));
        // A board no longer declared loses the scores the rebuild took back, and only those.
        self::assertNull($kit->scores->score('games', '2026-11', $this->member('member:1')));
        self::assertSame(1, $kit->scores->score('games', '2026-10', $this->member('member:1'))?->value);
        self::assertSame(4, $kit->scores->score('level', 'ever', $this->member('member:1'))?->value);
    }

    public function testAResetForgetsEverything(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-10-30 10:00', 2);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 10:00', 3);

        $kit->projection->reset(null);

        self::assertSame(0, $kit->scores->participants('points', 'ever'));
        self::assertSame(0, $kit->scores->participants('games', '2026-10'));
        self::assertSame([], $kit->scores->forget(null));
    }

    public function testARecountReadsTheStoreOnceEachScore(): void
    {
        $scores = new CountingScoreStore();
        $boards = Boards::fromArray(['games' => ['measure' => 'count', 'verbs' => ['game.played']], 'points' => ['measure' => 'total', 'verbs' => ['game.played']]]);
        $projection = new LeaderboardProjection($boards, $scores);

        foreach (['k1', 'k2', 'k3'] as $position => $key) {
            $projection->apply(new Entry($position + 1, EntryKind::Recorded, Activity::of($key, 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 10:00 UTC')), new DateTimeImmutable('2026-11-02 10:00 UTC')));
        }

        $scores->reads = 0;
        $projection->reset(null);

        self::assertSame(2, $scores->reads);
    }

    private function kit(): Kit
    {
        return new Kit(Boards::fromArray([
            'games' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'month', 'timezone' => 'America/Toronto'],
            'points' => ['measure' => 'total', 'verbs' => ['game.played', 'game.*']],
            'arcade' => ['measure' => 'best', 'verbs' => ['arcade.scored'], 'context' => ['ranked' => true], 'period' => 'week'],
            'level' => ['measure' => 'latest'],
        ]));
    }

    private function line(?Score $score): string
    {
        return $score instanceof Score ? \sprintf('%s %s %s=%d %s', $score->board, $score->member->toString(), $score->period, $score->value, $score->reachedAt->format('Y-m-d H:i')) : 'none';
    }

    private function member(string $member): ResourceReference
    {
        return ResourceReference::fromString($member);
    }
}
