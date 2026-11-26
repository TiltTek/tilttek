<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\TurnClock\Exception\ClockConflict;
use Tilttek\TurnClock\Exception\InvalidClock;
use Tilttek\TurnClock\Internal\Identifier;
use Tilttek\TurnClock\Internal\Millis;
use Tilttek\TurnClock\Mode;
use Tilttek\TurnClock\Overtime;
use Tilttek\TurnClock\PlayerClock;
use Tilttek\TurnClock\Store\InMemoryClockStore;
use Tilttek\TurnClock\TableClock;
use Tilttek\TurnClock\TimeControl;
use Tilttek\TurnClock\Vacation;

#[CoversClass(TimeControl::class)]
#[CoversClass(Vacation::class)]
#[CoversClass(PlayerClock::class)]
#[CoversClass(TableClock::class)]
#[CoversClass(InMemoryClockStore::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Millis::class)]
#[CoversClass(InvalidClock::class)]
#[CoversClass(ClockConflict::class)]
final class ValuesTest extends TestCase
{
    private const int DAY = 86_400_000;

    public function testATimeControlHasItsDefaults(): void
    {
        $control = new TimeControl(60_000);

        self::assertSame(60_000, $control->budget);
        self::assertSame(Mode::Fischer, $control->mode);
        self::assertSame(0, $control->increment);
        self::assertSame(Overtime::Continue, $control->overtime);
    }

    public function testABudgetIsOneSecondToThirtyOneDays(): void
    {
        self::assertSame(1_000, new TimeControl(1_000)->budget);
        self::assertSame(31 * self::DAY, new TimeControl(31 * self::DAY)->budget);
        foreach ([999, 31 * self::DAY + 1] as $budget) {
            try {
                new TimeControl($budget);
                self::fail('Out of bounds.');
            } catch (InvalidClock $exception) {
                self::assertSame('A budget is 1 second to 31 days.', $exception->getMessage());
            }
        }
    }

    public function testAnIncrementIsZeroToOneDay(): void
    {
        self::assertSame(0, new TimeControl(1_000, increment: 0)->increment);
        self::assertSame(self::DAY, new TimeControl(1_000, increment: self::DAY)->increment);
        foreach ([-1, self::DAY + 1] as $increment) {
            try {
                new TimeControl(1_000, increment: $increment);
                self::fail('Out of bounds.');
            } catch (InvalidClock $exception) {
                self::assertSame('An increment is 0 to 1 day.', $exception->getMessage());
            }
        }
    }

    public function testDaysPerTurnForfeit(): void
    {
        self::assertEquals(new TimeControl(3 * self::DAY, Mode::PerTurn, 0, Overtime::Forfeit), TimeControl::daysPerTurn(3));

        $this->expectException(InvalidClock::class);
        TimeControl::daysPerTurn(32);
    }

    public function testAVacationEndsAfterItStartsWithinSixtyDays(): void
    {
        $from = $this->at('2027-01-01 00:00');
        $vacation = new Vacation($from, $from->modify('+60 days'));
        self::assertSame('2027-03-02', $vacation->until->format('Y-m-d'));
        self::assertSame('2027-01-01', $vacation->from->format('Y-m-d'));
        self::assertCount(1, [new Vacation($from, $from->modify('+1 second'))]);

        foreach ([$from, $from->modify('-1 second'), $from->modify('+60 days +1 second')] as $until) {
            try {
                new Vacation($from, $until);
                self::fail('Out of bounds.');
            } catch (InvalidClock $exception) {
                self::assertSame('A vacation ends after it starts, at most 60 days later.', $exception->getMessage());
            }
        }
    }

    public function testAVacationOverlapsASpan(): void
    {
        $vacation = new Vacation(Millis::at(10_000), Millis::at(20_000));

        self::assertSame(5_000, $vacation->overlap(0, 15_000));
        self::assertSame(10_000, $vacation->overlap(0, 30_000));
        self::assertSame(5_000, $vacation->overlap(15_000, 30_000));
        self::assertSame(2_000, $vacation->overlap(12_000, 14_000));
        self::assertSame(0, $vacation->overlap(0, 10_000));
        self::assertSame(0, $vacation->overlap(25_000, 30_000));
    }

    public function testAPlayerStartsWithTheirBudget(): void
    {
        $player = new PlayerClock('ada', 60_000);

        self::assertSame(60_000, $player->remaining);
        self::assertSame(0, $player->turns);
        self::assertSame(0, $player->spent);
        self::assertSame([], $player->vacations);
    }

    public function testAPlayerIsCharged(): void
    {
        $player = new PlayerClock('ada', 60_000, 50_000, 2, 10_000);

        self::assertEquals(new PlayerClock('ada', 60_000, 40_000, 3, 20_000), $player->charged(40_000, 10_000, true));
        self::assertEquals(new PlayerClock('ada', 60_000, 40_000, 2, 20_000), $player->charged(40_000, 10_000, false));
        self::assertEquals(new PlayerClock('ada', 60_000, -1, 2, 10_000), $player->withRemaining(-1));
    }

    public function testThePlayersTurnsAndTimeAreNotNegative(): void
    {
        foreach ([[-1, 0], [0, -1]] as [$turns, $spent]) {
            try {
                new PlayerClock('ada', 60_000, null, $turns, $spent);
                self::fail('Negative.');
            } catch (InvalidClock $exception) {
                self::assertSame('The turns and the time spent are 0 or more.', $exception->getMessage());
            }
        }
    }

    public function testAPlayersBudgetIsBounded(): void
    {
        $this->expectException(InvalidClock::class);
        new PlayerClock('ada', 999);
    }

    public function testAPlayersVacationsAreSortedAndApart(): void
    {
        $first = new Vacation(Millis::at(10_000), Millis::at(20_000));
        $second = new Vacation(Millis::at(20_000), Millis::at(30_000));
        $player = new PlayerClock('ada', 60_000, 40_000, vacations: [$second, $first]);

        self::assertSame([$first, $second], $player->vacations);
        self::assertSame([$second], $player->withVacations([$second])->vacations);
        self::assertSame(40_000, $player->withVacations([$second])->remaining);
        self::assertSame(15_000, $player->counted(0, 35_000));
        self::assertSame(0, $player->counted(35_000, 30_000));

        $this->expectException(InvalidClock::class);
        $this->expectExceptionMessageIsOrContains("A player's vacations do not overlap.");
        new PlayerClock('ada', 60_000, vacations: [$first, new Vacation(Millis::at(19_999), Millis::at(25_000))]);
    }

    public function testAPlayerKeepsAtMostEightVacations(): void
    {
        $vacations = array_map(static fn (int $index): Vacation => new Vacation(Millis::at($index * 10_000), Millis::at($index * 10_000 + 1_000)), range(1, 8));
        self::assertCount(8, new PlayerClock('ada', 60_000, vacations: $vacations)->vacations);

        $this->expectException(InvalidClock::class);
        $this->expectExceptionMessageIsOrContains('A player keeps at most 8 vacations.');
        new PlayerClock('ada', 60_000, vacations: [...$vacations, new Vacation(Millis::at(100_000), Millis::at(101_000))]);
    }

    #[DataProvider('notIdentifiers')]
    public function testAnIdentifierHasItsShape(string $value): void
    {
        $this->expectException(InvalidClock::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a player is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.');
        new PlayerClock($value, 60_000);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1ada'];
        yield 'an address' => ['ada@example.org'];
        yield 'too long' => ['a'.str_repeat('d', 128)];
        yield 'a line after' => ["ada\n"];
    }

    public function testTheLongestIdentifierIsTaken(): void
    {
        $id = 'a'.str_repeat('d', 127);

        self::assertSame($id, new PlayerClock($id, 60_000)->id);
    }

    public function testAClockHasOneToTwentyPlayers(): void
    {
        $players = array_map(static fn (int $index): PlayerClock => new PlayerClock('p'.$index, 60_000), range(1, 20));
        self::assertCount(20, new TableClock('table', new TimeControl(60_000), $players)->players);

        foreach ([[], [...$players, new PlayerClock('p21', 60_000)]] as $list) {
            try {
                new TableClock('table', new TimeControl(60_000), $list);
                self::fail('Out of bounds.');
            } catch (InvalidClock $exception) {
                self::assertSame('A clock has 1 to 20 players.', $exception->getMessage());
            }
        }
    }

    public function testAClockHoldsEachPlayerOnce(): void
    {
        $this->expectException(InvalidClock::class);
        $this->expectExceptionMessageIsOrContains('The player ada comes twice on the clock.');
        new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000), new PlayerClock('ada', 60_000)]);
    }

    public function testTheCurrentPlayerIsOnTheClock(): void
    {
        $this->expectException(InvalidClock::class);
        $this->expectExceptionMessageIsOrContains('The current player zed is not on the clock.');
        new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)], 'zed');
    }

    public function testTheTimeOfATurnAndTheVersionAreNotNegative(): void
    {
        self::assertSame(0, new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)], turnElapsed: 0, version: 0)->version);
        foreach ([[-1, 0], [0, -1]] as [$elapsed, $version]) {
            try {
                new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)], turnElapsed: $elapsed, version: $version);
                self::fail('Negative.');
            } catch (InvalidClock $exception) {
                self::assertSame('The time of a turn and the version of a clock are 0 or more.', $exception->getMessage());
            }
        }
    }

    public function testAClockChecksItsIdentifier(): void
    {
        $this->expectException(InvalidClock::class);
        $this->expectExceptionMessageIsOrContains('of a clock');
        new TableClock('', new TimeControl(60_000), [new PlayerClock('ada', 60_000)]);
    }

    public function testAClockCountsTheTurnInProgress(): void
    {
        $vacation = new Vacation(Millis::at(15_000), Millis::at(18_000));
        $clock = new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000, 50_000, vacations: [$vacation]), new PlayerClock('bo', 60_000)], 'ada', 2_000, Millis::at(10_000));

        self::assertTrue($clock->isStarted());
        self::assertFalse($clock->isPaused());
        self::assertSame(9_000, $clock->elapsed(20_000));
        self::assertSame(41_000, $clock->remaining('ada', 20_000));
        self::assertSame(60_000, $clock->remaining('bo', 20_000));
        self::assertSame(0, $clock->remaining('zed', 20_000));
        self::assertSame($clock->players[1], $clock->player('bo'));
        self::assertNull($clock->player('zed'));
    }

    public function testAPausedClockCountsWhatWasCounted(): void
    {
        $clock = new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)], 'ada', 2_000);

        self::assertTrue($clock->isPaused());
        self::assertSame(2_000, $clock->elapsed(99_000));
    }

    public function testAStoppedClockCountsNothingMore(): void
    {
        $clock = new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000, 50_000)], 'ada', 2_000, Millis::at(10_000), stopped: true);

        self::assertFalse($clock->isPaused());
        self::assertSame(2_000, $clock->elapsed(99_000));
        self::assertSame(50_000, $clock->remaining('ada', 99_000));
    }

    public function testAClockNotStartedCountsNothing(): void
    {
        $clock = new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)], runningSince: Millis::at(10_000));

        self::assertFalse($clock->isStarted());
        self::assertFalse($clock->isPaused());
        self::assertSame(0, $clock->elapsed(99_000));
    }

    public function testAClockStartsAsItIsNotStartedNorStopped(): void
    {
        $clock = new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)]);

        self::assertNull($clock->current);
        self::assertSame(0, $clock->turnElapsed);
        self::assertNull($clock->runningSince);
        self::assertFalse($clock->expiryTold);
        self::assertFalse($clock->stopped);
        self::assertSame(0, $clock->version);
        self::assertFalse($clock->isPaused());
    }

    public function testADelayIsNotCharged(): void
    {
        $clock = new TableClock('table', new TimeControl(60_000, Mode::Delay, 5_000), [new PlayerClock('ada', 60_000)]);

        self::assertSame(0, $clock->charge(5_000));
        self::assertSame(0, $clock->charge(3_000));
        self::assertSame(1_000, $clock->charge(6_000));
        self::assertSame(6_000, new TableClock('table', new TimeControl(60_000, Mode::Bronstein, 5_000), $clock->players)->charge(6_000));
    }

    public function testAForfeitingClockShowsNoTimeBelowZero(): void
    {
        $clock = new TableClock('table', new TimeControl(10_000, overtime: Overtime::Forfeit), [new PlayerClock('ada', 10_000)], 'ada', 0, Millis::at(0));

        self::assertSame(0, $clock->remaining('ada', 15_000));
        self::assertFalse($clock->isOut($clock->players[0]));
        self::assertTrue($clock->isOut($clock->players[0]->withRemaining(0)));
        self::assertFalse(new TableClock('table', new TimeControl(10_000), [new PlayerClock('ada', 10_000, -5)])->isOut(new PlayerClock('ada', 10_000, -5)));
    }

    public function testAClockChangesWhatItIsGiven(): void
    {
        $clock = new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000), new PlayerClock('bo', 60_000)], version: 4);

        $changed = $clock->with($clock->players, 'bo', 3_000, Millis::at(1_000), true, true);
        self::assertSame('bo', $changed->current);
        self::assertSame(3_000, $changed->turnElapsed);
        self::assertSame(1_000, Millis::of($changed->runningSince ?? self::fail()));
        self::assertTrue($changed->expiryTold);
        self::assertTrue($changed->stopped);
        self::assertSame(4, $changed->version);
        self::assertSame($clock->control, $changed->control);

        $player = $changed->withPlayer(new PlayerClock('bo', 60_000, 1_000));
        self::assertSame(1_000, $player->player('bo')?->remaining);
        self::assertSame(60_000, $player->player('ada')?->remaining);
        self::assertSame('bo', $player->current);
        self::assertSame(3_000, $player->turnElapsed);
        self::assertTrue($player->expiryTold);
        self::assertTrue($player->stopped);

        $versioned = $changed->withVersion(9);
        self::assertSame(9, $versioned->version);
        self::assertSame('bo', $versioned->current);
        self::assertSame(3_000, $versioned->turnElapsed);
        self::assertTrue($versioned->expiryTold);
        self::assertTrue($versioned->stopped);
        self::assertSame(1_000, Millis::of($versioned->runningSince ?? self::fail()));
    }

    public function testTheStoreKeepsEachClockAtItsVersion(): void
    {
        $store = new InMemoryClockStore();
        $clock = new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)]);

        self::assertNull($store->clock('table'));
        $kept = $store->save($clock);
        self::assertSame(1, $kept->version);
        self::assertSame($kept, $store->clock('table'));
        self::assertSame(2, $store->save($kept)->version);

        $this->expectException(ClockConflict::class);
        $this->expectExceptionMessageIsOrContains('The clock table changed since it was read.');
        $store->save($kept);
    }

    public function testTheStoreRefusesANewClockOfAKeptIdentifier(): void
    {
        $store = new InMemoryClockStore();
        $store->save(new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)]));

        $this->expectException(ClockConflict::class);
        $store->save(new TableClock('table', new TimeControl(60_000), [new PlayerClock('ada', 60_000)]));
    }

    public function testMillisecondsRoundTrip(): void
    {
        $moment = Millis::at(1_700_000_000_042);

        self::assertSame('2023-11-14T22:13:20.042+00:00', $moment->format(\DATE_RFC3339_EXTENDED));
        self::assertSame(1_700_000_000_042, Millis::of($moment));
        self::assertSame(1_700_000_000_042, Millis::of(new DateTimeImmutable('2023-11-14 17:13:20.042', new DateTimeZone('America/Toronto'))));
        self::assertSame('2023-11-14T22:13:20.900+00:00', Millis::at(1_700_000_000_900)->format(\DATE_RFC3339_EXTENDED));
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }
}
