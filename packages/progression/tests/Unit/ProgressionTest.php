<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Erasure;
use Tilttek\Progression\Exception\UnknownStreak;
use Tilttek\Progression\Exception\UnknownTrack;
use Tilttek\Progression\Experience\Gain;
use Tilttek\Progression\Level;
use Tilttek\Progression\Progression;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\SeasonResult;
use Tilttek\Progression\Standing;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Streak\StreakStatus;
use Tilttek\Progression\Tests\Unit\Fixture\Kit;
use Tilttek\Progression\Tracks;

#[CoversClass(Progression::class)]
#[CoversClass(Standing::class)]
#[CoversClass(SeasonResult::class)]
#[CoversClass(Erasure::class)]
final class ProgressionTest extends TestCase
{
    public function testWhereASubjectStands(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-10-30 10:00', quantity: 3);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-02 10:00', quantity: 2);
        $kit->clock->set('2026-11-07 12:00:00 UTC');

        $player = $kit->progression->standing($this->ref('member:1'), 'player');
        self::assertSame($kit->tracks, $kit->progression->tracks());
        self::assertSame($kit->tracks->get('player'), $player->track);
        self::assertNull($player->season);
        self::assertEquals(new Level(3, 250, 200, 300), $player->level);
        self::assertSame(250, $player->lifetime);

        $monthly = $kit->progression->standing($this->ref('member:1'), 'monthly');
        self::assertSame('2026-11', $monthly->season?->code);
        self::assertEquals(new Level(1, 20, 0, 50), $monthly->level);
        self::assertSame(50, $monthly->lifetime);

        $summer = $kit->progression->standing($this->ref('member:1'), 'events');
        self::assertNull($summer->season);
        self::assertEquals(new Level(1, 0, 0, 10), $summer->level);
        self::assertSame(5, $summer->lifetime);

        self::assertSame(['events', 'monthly', 'player'], array_map(static fn (Standing $standing): string => $standing->track->name, $kit->progression->standings($this->ref('member:1'))));

        $kit->clock->set('2026-12-10 12:00:00 UTC');
        $event = $kit->progression->standing($this->ref('member:1'), 'events');
        self::assertSame('winter', $event->season?->code);
        self::assertEquals(new Level(1, 0, 0, 10), $event->level);
    }

    public function testThePastSeasons(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-09-30 10:00', quantity: 3);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-02 10:00', quantity: 2);
        $kit->record('k3', 'member:1', 'game.played', '2026-08-15 10:00', quantity: 6);
        $kit->experience->add(new Gain('monthly', 'games', $this->ref('member:1'), $this->ref('member:1'), 'k9', 1, new DateTimeImmutable('2026-10-01 UTC'), 'not-a-month'));
        $kit->clock->set('2026-11-07 12:00:00 UTC');

        $history = $kit->progression->history($this->ref('member:1'), 'monthly');

        self::assertSame(['2026-08', '2026-09'], array_map(static fn (SeasonResult $result): string => $result->season->code, $history));
        self::assertEquals(new Level(2, 60, 50, 100), $history[0]->level);
        self::assertEquals(new Level(1, 30, 0, 50), $history[1]->level);
        self::assertSame([], $kit->progression->history($this->ref('member:1'), 'player'));
        $kit->experience->add(new Gain('player', 'games', $this->ref('member:1'), $this->ref('member:1'), 'k8', 1, new DateTimeImmutable('2026-10-01 UTC'), '2026-10'));
        self::assertSame([], $kit->progression->history($this->ref('member:1'), 'player'));

        self::assertSame(3, $this->pastSeasons($kit, '2026-12-01 05:00:00 UTC'));
        self::assertSame(2, $this->pastSeasons($kit, '2026-12-01 04:59:59 UTC'));
    }

    public function testMilestonesAndGains(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', quantity: 3);

        self::assertSame(['first', 'veteran'], array_map(static fn (ReachedMilestone $reached): string => $reached->milestone, $kit->progression->milestones($this->ref('member:1'))));
        self::assertSame(['events', 'monthly', 'player'], array_map(static fn (Gain $gain): string => $gain->track, $kit->progression->gains($this->ref('member:1'))));
    }

    public function testStreaks(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00');
        $kit->record('k2', 'member:1', 'game.played', '2026-11-03 10:00');
        $kit->clock->set('2026-11-04 12:00:00 UTC');

        self::assertSame(2, $kit->progression->streak($this->ref('member:1'), 'daily')->current);
        self::assertSame(['daily', 'weekly'], array_map(static fn (StreakStatus $status): string => $status->streak, $kit->progression->streaks($this->ref('member:1'))));
        self::assertSame([2, 1], array_map(static fn (StreakStatus $status): int => $status->current, $kit->progression->streaks($this->ref('member:1'))));

        self::assertTrue($kit->progression->freeze($this->ref('member:1'), 'daily', new DateTimeImmutable('2026-11-04 12:00 UTC')));
        self::assertFalse($kit->progression->freeze($this->ref('member:1'), 'daily', new DateTimeImmutable('2026-11-04 13:00 UTC')));
        $kit->clock->set('2026-11-05 12:00:00 UTC');
        self::assertSame(0, $kit->progression->sweep());
        self::assertTrue($kit->progression->thaw($this->ref('member:1'), 'daily', new DateTimeImmutable('2026-11-04 12:00 UTC')));
        self::assertFalse($kit->progression->thaw($this->ref('member:1'), 'daily', new DateTimeImmutable('2026-11-04 12:00 UTC')));
        self::assertSame(1, $kit->progression->sweep());
        self::assertSame(['broken daily member:1 2 20760 2026-11-05 12:00'], array_values(array_filter($kit->recorder->told, static fn (string $told): bool => str_starts_with($told, 'broken'))));
    }

    public function testAnUnknownStreak(): void
    {
        $this->expectExceptionObject(UnknownStreak::named('monthly'));
        $this->kit()->progression->streak($this->ref('member:1'), 'monthly');
    }

    public function testAnUnknownStreakToFreeze(): void
    {
        $this->expectExceptionObject(UnknownStreak::named('monthly'));
        $this->kit()->progression->freeze($this->ref('member:1'), 'monthly', new DateTimeImmutable());
    }

    public function testAnUnknownStreakToThaw(): void
    {
        $this->expectExceptionObject(UnknownStreak::named('monthly'));
        $this->kit()->progression->thaw($this->ref('member:1'), 'monthly', new DateTimeImmutable());
    }

    public function testAnUnknownTrack(): void
    {
        $this->expectExceptionObject(UnknownTrack::named('author'));
        $this->kit()->progression->standing($this->ref('member:1'), 'author');
    }

    public function testAnUnknownTrackHasNoHistory(): void
    {
        $this->expectExceptionObject(UnknownTrack::named('author'));
        $this->kit()->progression->history($this->ref('member:1'), 'author');
    }

    public function testAMemberIsErased(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', quantity: 3);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-02 10:00');
        $kit->progression->freeze($this->ref('member:1'), 'daily', new DateTimeImmutable('2026-11-03 10:00 UTC'));

        self::assertEquals(new Erasure(3, 2, 5), $kit->progression->erase($this->ref('member:1')));
        self::assertSame(0, $kit->progression->standing($this->ref('member:1'), 'player')->lifetime);
        self::assertSame(50, $kit->progression->standing($this->ref('member:2'), 'player')->lifetime);
        self::assertSame(0, $kit->progression->streak($this->ref('member:1'), 'daily')->best);
        self::assertEquals(new Erasure(), $kit->progression->erase($this->ref('member:1')));
    }

    private function kit(): Kit
    {
        return new Kit(Tracks::fromArray([
            'player' => [
                'curve' => ['type' => 'linear', 'step' => 100, 'levels' => 10],
                'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 50]],
                'milestones' => ['first' => ['experience' => 50], 'veteran' => ['level' => 2]],
            ],
            'monthly' => [
                'curve' => ['type' => 'linear', 'step' => 50],
                'seasons' => ['length' => 'month'],
                'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 10]],
            ],
            'events' => [
                'curve' => ['type' => 'linear', 'step' => 10],
                'seasons' => ['named' => [
                    ['code' => 'autumn', 'start' => '2026-10-01', 'end' => '2026-11-01'],
                    ['code' => 'winter', 'start' => '2026-12-01', 'end' => '2027-03-01'],
                ]],
                'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 1]],
            ],
        ], 'America/Toronto'), StreakRules::fromArray([
            'daily' => ['verbs' => ['game.played']],
            'weekly' => ['verbs' => ['game.played'], 'unit' => 'week'],
        ]));
    }

    private function pastSeasons(Kit $kit, string $now): int
    {
        $kit->clock->set($now);

        return \count($kit->progression->history($this->ref('member:1'), 'monthly'));
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
