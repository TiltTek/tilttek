<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Streak;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Store\InMemoryStreakStore;
use Tilttek\Progression\Streak\StreakBroken;
use Tilttek\Progression\Streak\StreakGesture;
use Tilttek\Progression\Streak\StreakKeeper;
use Tilttek\Progression\Streak\StreakRule;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Streak\StreakState;
use Tilttek\Progression\Streak\StreakUnit;
use Tilttek\Progression\Tests\Unit\Fixture\Recorder;

#[CoversClass(StreakKeeper::class)]
#[CoversClass(StreakBroken::class)]
#[CoversClass(StreakState::class)]
final class StreakKeeperTest extends TestCase
{
    private InMemoryStreakStore $store;

    private Recorder $recorder;

    private StreakKeeper $keeper;

    private StreakRule $daily;

    private ResourceReference $member;

    protected function setUp(): void
    {
        $this->store = new InMemoryStreakStore();
        $this->recorder = new Recorder();
        $rules = StreakRules::fromArray(['daily' => ['verbs' => ['game.played']], 'weekly' => ['verbs' => ['game.played'], 'unit' => 'week']], 'Europe/Paris');
        $this->keeper = new StreakKeeper($rules, $this->store, $this->recorder);
        $this->daily = $rules->get('daily');
        $this->member = ResourceReference::fromString('member:1');
    }

    public function testDaysInARow(): void
    {
        $this->record('k1', '2026-11-02 10:00');
        $this->record('k2', '2026-11-03 10:00');
        $this->record('k3', '2026-11-03 18:00');

        $status = $this->keeper->status($this->daily, $this->member, $this->at('2026-11-04 10:00'));

        self::assertSame($this->keeper->rules()->get('daily'), $this->daily);
        self::assertSame([2, 2], [$status->current, $status->best]);
        self::assertEquals(new StreakState('daily', $this->member, 2, 2, 20759, 20760, 'UTC'), $this->store->state('daily', $this->member));
        self::assertSame([], $this->recorder->told);
    }

    public function testAGestureAfterAGapTellsTheBrokenRun(): void
    {
        $this->record('k1', '2026-11-02 10:00');
        $this->record('k2', '2026-11-03 10:00');
        $this->record('k3', '2026-11-05 10:00');
        $this->record('k4', '2026-11-06 10:00');

        self::assertSame(['broken daily member:1 2 20760 2026-11-05 10:00'], $this->recorder->told);
        self::assertSame(2, $this->store->state('daily', $this->member)?->current);
        self::assertSame(20762, $this->store->state('daily', $this->member)->start);
    }

    public function testALateGestureExtendsTheRun(): void
    {
        $this->record('k1', '2026-11-03 10:00');
        $this->record('k2', '2026-11-02 10:00', '2026-11-03 11:00');

        self::assertSame([], $this->recorder->told);
        self::assertSame(2, $this->store->state('daily', $this->member)?->current);
    }

    public function testALateGestureSeesTheRunOverAndTellsIt(): void
    {
        $this->record('k1', '2026-11-03 10:00');
        $this->record('k2', '2026-11-01 10:00', '2026-11-06 10:00');

        self::assertSame(['broken daily member:1 1 20760 2026-11-06 10:00'], $this->recorder->told);
        self::assertSame(0, $this->store->state('daily', $this->member)?->current);
        self::assertNull($this->store->state('daily', $this->member)->start);
    }

    public function testAQuietRecordTellsNothing(): void
    {
        $this->record('k1', '2026-11-02 10:00');
        $this->keeper->record($this->daily, $this->member, 'k2', $this->at('2026-11-05 10:00'), new DateTimeZone('UTC'), $this->at('2026-11-05 10:00'), false);

        self::assertSame([], $this->recorder->told);
        self::assertSame(1, $this->store->state('daily', $this->member)?->current);
    }

    public function testASweepFindsTheBrokenStreaksOnce(): void
    {
        $this->record('k1', '2026-11-02 10:00');
        $this->record('k2', '2026-11-03 10:00');
        $this->store->save(new StreakState('daily', ResourceReference::fromString('member:9'), 0, 3, null, 20700, 'UTC'));

        self::assertSame(0, $this->keeper->sweep($this->at('2026-11-04 23:00')));
        self::assertSame(1, $this->keeper->sweep($this->at('2026-11-05 00:00')));
        self::assertSame(0, $this->keeper->sweep($this->at('2026-11-06 00:00')));
        self::assertSame(['broken daily member:1 2 20760 2026-11-05 00:00'], $this->recorder->told);
        self::assertSame(0, $this->store->state('daily', $this->member)?->current);
        self::assertSame(2, $this->store->state('daily', $this->member)->best);
    }

    public function testAKeeperWithoutAnObserver(): void
    {
        $keeper = new StreakKeeper($this->keeper->rules(), $this->store);
        $keeper->record($this->daily, $this->member, 'k1', $this->at('2026-11-02 10:00'), new DateTimeZone('UTC'), $this->at('2026-11-02 10:00'), true);

        self::assertSame(1, $keeper->sweep($this->at('2026-11-05 10:00')));
    }

    public function testASweepReadsTheMembersTimezone(): void
    {
        // 22:00 in Los Angeles on the 7th, 06:00 UTC on the 8th.
        $this->keeper->record($this->daily, $this->member, 'k1', $this->at('2026-11-08 06:00'), new DateTimeZone('America/Los_Angeles'), $this->at('2026-11-08 06:00'), true);

        self::assertSame(0, $this->keeper->sweep($this->at('2026-11-09 05:00')));
        self::assertSame(1, $this->keeper->sweep($this->at('2026-11-09 09:00')));
        self::assertSame('America/Los_Angeles', $this->store->state('daily', $this->member)?->timezone);
    }

    public function testASweepDiscardsAStateWithoutGestures(): void
    {
        $this->store->save(new StreakState('daily', $this->member, 4, 4, 20700, 20703, 'UTC'));

        self::assertSame(0, $this->keeper->sweep($this->at('2026-11-09 05:00')));
        self::assertNull($this->store->state('daily', $this->member));
        self::assertSame([], $this->recorder->told);
    }

    public function testACancellationRecountsSilently(): void
    {
        $this->record('k1', '2026-11-02 10:00');
        $this->record('k2', '2026-11-03 10:00');
        $this->record('k3', '2026-11-04 10:00');

        $this->keeper->cancel('k2', $this->at('2026-11-04 12:00'));

        self::assertSame([], $this->recorder->told);
        self::assertSame(1, $this->store->state('daily', $this->member)?->current);
        self::assertSame(1, $this->store->state('weekly', $this->member)?->current);

        $this->keeper->cancel('k3', $this->at('2026-11-05 12:00'));
        self::assertSame([], $this->recorder->told);
        self::assertSame(0, $this->store->state('daily', $this->member)?->current);

        $this->keeper->cancel('k1', $this->at('2026-11-05 12:00'));
        self::assertNull($this->store->state('daily', $this->member));
    }

    public function testACancellationOfAStreakNoLongerConfigured(): void
    {
        $this->store->add(new StreakGesture('retired', $this->member, 'k1', 20760, $this->at('2026-11-03 10:00')));
        $this->record('k1', '2026-11-03 10:00');

        $this->keeper->cancel('k1', $this->at('2026-11-04 12:00'));

        self::assertSame([], $this->store->units('retired', $this->member));
        self::assertNull($this->store->state('daily', $this->member));
    }

    public function testAFrozenDayKeepsTheRun(): void
    {
        $this->record('k1', '2026-11-02 10:00');
        $this->record('k2', '2026-11-03 10:00');

        // Without a state, the day is read in the streaks' timezone: 23:30 UTC on the 3rd is the 4th in Paris.
        self::assertTrue($this->keeper->freeze($this->daily, ResourceReference::fromString('member:2'), $this->at('2026-11-03 23:30')));
        self::assertSame([20761], $this->store->frozen('daily', ResourceReference::fromString('member:2')));
        self::assertTrue($this->keeper->freeze($this->daily, $this->member, $this->at('2026-11-04 23:30')));
        self::assertFalse($this->keeper->freeze($this->daily, $this->member, $this->at('2026-11-04 10:00')));
        self::assertSame(2, $this->keeper->status($this->daily, $this->member, $this->at('2026-11-05 10:00'))->current);
        self::assertSame(0, $this->keeper->sweep($this->at('2026-11-05 10:00')));
        self::assertTrue($this->keeper->thaw($this->daily, $this->member, $this->at('2026-11-04 12:00')));
        self::assertFalse($this->keeper->thaw($this->daily, $this->member, $this->at('2026-11-04 12:00')));
        self::assertSame(0, $this->keeper->status($this->daily, $this->member, $this->at('2026-11-05 10:00'))->current);
        self::assertSame(1, $this->keeper->sweep($this->at('2026-11-05 10:00')));
    }

    public function testTheStatusReadsTheTimezoneOfTheLastGesture(): void
    {
        $this->keeper->record($this->daily, $this->member, 'k1', $this->at('2026-11-03 10:00'), new DateTimeZone('Asia/Tokyo'), $this->at('2026-11-03 10:00'), true);

        // 16:00 UTC on the 4th is the 5th in Tokyo, the 4th in Paris.
        self::assertSame(0, $this->keeper->status($this->daily, $this->member, $this->at('2026-11-04 16:00'))->current);
        self::assertSame(1, $this->keeper->status($this->daily, ResourceReference::fromString('member:2'), $this->at('2026-11-04 16:00'))->graceLeft + 1);
        self::assertSame(StreakUnit::Day, $this->daily->unit);
    }

    public function testRebuildsForgetTheGestures(): void
    {
        $this->record('k1', '2026-11-02 10:00');
        $this->record('k2', '2026-11-03 10:00');

        $this->keeper->forgetSince($this->at('2026-11-03 00:00'));

        self::assertSame([20759], $this->store->units('daily', $this->member));
    }

    private function record(string $key, string $at, ?string $now = null): void
    {
        foreach ($this->keeper->rules()->all() as $rule) {
            $this->keeper->record($rule, $this->member, $key, $this->at($at), new DateTimeZone('UTC'), $this->at($now ?? $at), true);
        }

        $this->recorder->told = array_values(array_filter($this->recorder->told, static fn (string $told): bool => !str_contains($told, 'weekly')));
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }
}
