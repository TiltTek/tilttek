<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\TurnClock\Event\ClockPaused;
use Tilttek\TurnClock\Event\ClockResumed;
use Tilttek\TurnClock\Event\ClockStarted;
use Tilttek\TurnClock\Event\ClockStopped;
use Tilttek\TurnClock\Event\TimeAdjusted;
use Tilttek\TurnClock\Event\TimeExpired;
use Tilttek\TurnClock\Event\TurnEnded;
use Tilttek\TurnClock\Event\TurnStarted;
use Tilttek\TurnClock\Event\VacationDeclared;
use Tilttek\TurnClock\Exception\ClockNotFound;
use Tilttek\TurnClock\Exception\ClockRefused;
use Tilttek\TurnClock\Exception\InvalidClock;
use Tilttek\TurnClock\Mode;
use Tilttek\TurnClock\Overtime;
use Tilttek\TurnClock\PlayerClock;
use Tilttek\TurnClock\Store\InMemoryClockStore;
use Tilttek\TurnClock\TableClock;
use Tilttek\TurnClock\Tests\Unit\Fixture\Observed;
use Tilttek\TurnClock\TimeControl;
use Tilttek\TurnClock\TurnClock;
use Tilttek\TurnClock\Vacation;
use Tilttek\TurnClock\View\ClockView;
use Tilttek\TurnClock\View\PlayerTime;

#[CoversClass(TurnClock::class)]
#[CoversClass(ClockNotFound::class)]
#[CoversClass(ClockRefused::class)]
#[CoversClass(ClockView::class)]
#[CoversClass(PlayerTime::class)]
#[CoversClass(ClockStarted::class)]
#[CoversClass(ClockPaused::class)]
#[CoversClass(ClockResumed::class)]
#[CoversClass(ClockStopped::class)]
#[CoversClass(TurnStarted::class)]
#[CoversClass(TurnEnded::class)]
#[CoversClass(TimeExpired::class)]
#[CoversClass(VacationDeclared::class)]
#[CoversClass(TimeAdjusted::class)]
final class TurnClockTest extends TestCase
{
    private const int SECOND = 1_000;

    private const int DAY = 86_400_000;

    private MockClock $now;

    private Observed $observed;

    private TurnClock $clocks;

    protected function setUp(): void
    {
        $store = new InMemoryClockStore();
        $this->now = new MockClock(new DateTimeImmutable('2027-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->observed = new Observed();
        $this->clocks = new TurnClock($store, $this->now, $this->observed);
    }

    public function testACreatedClockGivesEveryPlayerTheirBudget(): void
    {
        $clock = $this->clocks->create('table', ['ada', 'bo', 'cy'], new TimeControl(60 * self::SECOND), ['bo' => 90 * self::SECOND]);

        self::assertSame(1, $clock->version);
        self::assertFalse($clock->isStarted());
        self::assertSame([60_000, 90_000, 60_000], array_map(static fn (PlayerClock $player): int => $player->remaining, $clock->players));
        self::assertSame([60_000, 90_000, 60_000], array_map(static fn (PlayerClock $player): int => $player->budget, $clock->players));
        self::assertSame([], $this->observed->take());
        self::assertSame($clock, $this->clocks->clock('table'));
    }

    public function testABudgetIsForAPlayerOnTheClock(): void
    {
        $this->expectException(InvalidClock::class);
        $this->expectExceptionMessageIsOrContains('The budget of zed is for a player not on the clock.');
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND), ['zed' => 90 * self::SECOND]);
    }

    public function testAClockIdentifierIsTakenOnce(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('A clock has the identifier table.');
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));
    }

    public function testAnUnknownClockIsNotFound(): void
    {
        $this->expectException(ClockNotFound::class);
        $this->expectExceptionMessageIsOrContains('No clock has the identifier nope.');
        $this->clocks->clock('nope');
    }

    public function testTheFirstSeatStartsByDefault(): void
    {
        $this->clocks->create('table', ['ada', 'bo'], new TimeControl(60 * self::SECOND));

        $clock = $this->clocks->start('table');

        self::assertSame('ada', $clock->current);
        self::assertSame($this->now->now()->format('Uv'), $clock->runningSince?->format('Uv'));
        self::assertEquals([new ClockStarted('table'), new TurnStarted('table', 'ada')], $this->observed->take());
    }

    public function testAnotherPlayerMayStart(): void
    {
        $this->clocks->create('table', ['ada', 'bo'], new TimeControl(60 * self::SECOND));

        self::assertSame('bo', $this->clocks->start('table', 'bo')->current);
    }

    public function testAnUnknownPlayerDoesNotStart(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));

        $this->expectException(ClockNotFound::class);
        $this->expectExceptionMessageIsOrContains('The clock table has no player zed.');
        $this->clocks->start('table', 'zed');
    }

    public function testAClockStartsOnce(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));
        $this->clocks->start('table');

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('The clock table is started already.');
        $this->clocks->start('table');
    }

    public function testAStoppedClockDoesNotStart(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));
        $this->clocks->stop('table');

        $this->expectException(ClockRefused::class);
        $this->clocks->start('table');
    }

    public function testAPlayerOutOfTimeDoesNotStart(): void
    {
        $this->clocks->create('table', ['ada', 'bo'], new TimeControl(60 * self::SECOND, overtime: Overtime::Forfeit));
        $this->clocks->adjust('table', 'ada', -60 * self::SECOND);

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('The player ada is out of time.');
        $this->clocks->start('table');
    }

    public function testFischerAddsTheIncrementAfterEachTurn(): void
    {
        $this->started(new TimeControl(60 * self::SECOND, Mode::Fischer, 5 * self::SECOND));

        $this->now->sleep(10);
        $clock = $this->clocks->pass('table', 'ada');

        self::assertSame(55_000, $clock->player('ada')?->remaining);
        self::assertSame(1, $clock->player('ada')->turns);
        self::assertSame(10_000, $clock->player('ada')->spent);
        self::assertSame('bo', $clock->current);
        self::assertSame(0, $clock->turnElapsed);
        self::assertSame($this->now->now()->format('Uv'), $clock->runningSince?->format('Uv'));
        self::assertEquals([new TurnEnded('table', 'ada', 10_000), new TurnStarted('table', 'bo')], $this->observed->take());
    }

    public function testBronsteinGivesBackTheTimeSpentUpToTheIncrement(): void
    {
        $this->started(new TimeControl(60 * self::SECOND, Mode::Bronstein, 5 * self::SECOND));

        $this->now->sleep(3);
        self::assertSame(60_000, $this->clocks->pass('table', 'ada')->player('ada')?->remaining);
        $this->clocks->pass('table', 'bo');
        $this->now->sleep(10);

        self::assertSame(55_000, $this->clocks->pass('table', 'ada')->player('ada')->remaining);
    }

    public function testADelayRunsTheBudgetOnlyAfterIt(): void
    {
        $this->started(new TimeControl(60 * self::SECOND, Mode::Delay, 5 * self::SECOND));

        $this->now->sleep(3);
        self::assertSame(60_000, $this->clocks->view('table')->players[0]->remaining);
        self::assertSame(60_000, $this->clocks->pass('table', 'ada')->player('ada')?->remaining);
        $this->clocks->pass('table', 'bo');
        $this->now->sleep(8);

        self::assertSame(57_000, $this->clocks->pass('table', 'ada')->player('ada')->remaining);
    }

    public function testADelayCountsInTheMomentOfExpiry(): void
    {
        $this->started(new TimeControl(60 * self::SECOND, Mode::Delay, 5 * self::SECOND));
        $this->now->sleep(2);

        self::assertSame('2027-01-01T12:01:05.000+00:00', $this->clocks->view('table')->toArray()['expiresAt']);

        $this->now->sleep(10);
        self::assertSame('2027-01-01T12:01:05.000+00:00', $this->clocks->view('table')->toArray()['expiresAt']);
    }

    public function testEachTurnHasTheWholeBudgetPerTurn(): void
    {
        $this->started(TimeControl::daysPerTurn(2));

        $this->now->sleep(self::DAY / 1000);
        $clock = $this->clocks->pass('table', 'ada');
        self::assertSame(self::DAY, $clock->player('ada')?->remaining);
        self::assertSame(2 * self::DAY, $clock->player('bo')?->remaining);

        $this->clocks->pass('table', 'bo');
        self::assertSame(2 * self::DAY, $this->clocks->clock('table')->player('ada')?->remaining);
    }

    public function testAPlayerOutOfDaysForfeitsWhenChecked(): void
    {
        $this->started(TimeControl::daysPerTurn(1));
        $this->now->sleep(self::DAY / 1000 - 1);
        self::assertSame(2, $this->clocks->check('table')->version);

        $this->now->sleep(1);
        $clock = $this->clocks->check('table');

        self::assertSame(0, $clock->player('ada')?->remaining);
        self::assertTrue($clock->isOut($clock->player('ada')));
        self::assertSame('bo', $clock->current);
        self::assertEquals([new TimeExpired('table', 'ada'), new TurnEnded('table', 'ada', self::DAY), new TurnStarted('table', 'bo')], $this->observed->take());
    }

    public function testAClockStillInTimeIsLeftAsItIs(): void
    {
        $started = $this->started(new TimeControl(10 * self::SECOND));
        $this->now->sleep(9);

        self::assertSame($started, $this->clocks->check('table'));
        self::assertSame([], $this->observed->take());
    }

    public function testAClockNotStartedIsLeftAsItIsWhenChecked(): void
    {
        $created = $this->clocks->create('table', ['ada'], new TimeControl(10 * self::SECOND));

        self::assertSame($created, $this->clocks->check('table'));
    }

    public function testAStoppedClockIsLeftAsItIsWhenChecked(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->now->sleep(20);
        $stopped = $this->clocks->stop('table');

        self::assertSame($stopped, $this->clocks->check('table'));
    }

    public function testOvertimeIsCountedBelowZeroAndToldOnce(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->now->sleep(12);

        $checked = $this->clocks->check('table');
        self::assertTrue($checked->expiryTold);
        self::assertEquals([new TimeExpired('table', 'ada')], $this->observed->take());
        self::assertSame($checked, $this->clocks->check('table'));

        $this->now->sleep(3);
        $clock = $this->clocks->pass('table', 'ada');

        self::assertSame(-5_000, $clock->player('ada')?->remaining);
        self::assertFalse($clock->expiryTold);
        self::assertEquals([new TurnEnded('table', 'ada', 15_000), new TurnStarted('table', 'bo')], $this->observed->take());
        $view = $this->clocks->view('table');
        self::assertSame(-5_000, $view->players[0]->remaining);
        self::assertSame(5_000, $view->players[0]->overtime);
        self::assertFalse($view->players[0]->out);
    }

    public function testAnExpiryAtTheEndOfTheTurnIsToldWithIt(): void
    {
        $this->started(new TimeControl(10 * self::SECOND, Mode::Fischer, 20 * self::SECOND));
        $this->now->sleep(10);

        $clock = $this->clocks->pass('table', 'ada');

        self::assertSame(20_000, $clock->player('ada')?->remaining);
        self::assertEquals([new TimeExpired('table', 'ada'), new TurnEnded('table', 'ada', 10_000), new TurnStarted('table', 'bo')], $this->observed->take());
    }

    public function testAPlayerAlreadyInOvertimeIsNotToldAgain(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->now->sleep(12);
        $this->clocks->pass('table', 'ada');
        $this->clocks->pass('table', 'bo');

        $this->observed->take();
        $this->now->sleep(1);

        $this->clocks->pass('table', 'ada');

        self::assertEquals([new TurnEnded('table', 'ada', 1_000), new TurnStarted('table', 'bo')], $this->observed->take());
        $this->clocks->pass('table', 'bo');
        $this->now->sleep(1);
        self::assertSame($this->clocks->clock('table'), $this->clocks->check('table'));
        self::assertSame([], \array_slice($this->observed->take(), 2));
    }

    public function testAPlayerOutOfTimeIsSkipped(): void
    {
        $this->clocks->create('table', ['ada', 'bo', 'cy'], new TimeControl(10 * self::SECOND, Mode::Fischer, self::SECOND, Overtime::Forfeit));
        $this->clocks->start('table');

        $this->now->sleep(11);

        $clock = $this->clocks->pass('table', 'ada');
        self::assertSame(0, $clock->player('ada')?->remaining);
        self::assertSame('bo', $clock->current);

        self::assertSame('cy', $this->clocks->pass('table', 'bo')->current);
        self::assertSame('bo', $this->clocks->pass('table', 'cy')->current);
        self::assertTrue($this->clocks->view('table')->players[0]->out);
    }

    public function testTheLastPlayerInTimePlaysOn(): void
    {
        $this->started(new TimeControl(10 * self::SECOND, overtime: Overtime::Forfeit));
        $this->now->sleep(11);
        $this->clocks->pass('table', 'ada');
        $this->observed->take();

        $clock = $this->clocks->pass('table', 'bo');

        self::assertSame('bo', $clock->current);
        self::assertFalse($clock->stopped);
        self::assertEquals([new TurnEnded('table', 'bo', 0), new TurnStarted('table', 'bo')], $this->observed->take());
    }

    public function testTheClockStopsWhenEveryPlayerIsOut(): void
    {
        $this->started(new TimeControl(10 * self::SECOND, overtime: Overtime::Forfeit));
        $this->now->sleep(11);
        $this->clocks->pass('table', 'ada');
        $this->now->sleep(10);
        $this->observed->take();

        $clock = $this->clocks->pass('table', 'bo');

        self::assertTrue($clock->stopped);
        self::assertSame('bo', $clock->current);
        self::assertNull($clock->runningSince);
        self::assertSame(0, $clock->turnElapsed);
        self::assertEquals([new TimeExpired('table', 'bo'), new TurnEnded('table', 'bo', 10_000), new ClockStopped('table')], $this->observed->take());
    }

    public function testAPlayerNamesWhoGoesNext(): void
    {
        $this->clocks->create('table', ['ada', 'bo', 'cy'], new TimeControl(60 * self::SECOND));
        $this->clocks->start('table');

        self::assertSame('cy', $this->clocks->pass('table', 'ada', 'cy')->current);
    }

    public function testThePlayerNamedIsAnother(): void
    {
        $this->started(new TimeControl(60 * self::SECOND));

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('The player ada cannot take the turn.');
        $this->clocks->pass('table', 'ada', 'ada');
    }

    public function testThePlayerNamedIsInTime(): void
    {
        $this->clocks->create('table', ['ada', 'bo', 'cy'], new TimeControl(60 * self::SECOND, overtime: Overtime::Forfeit));
        $this->clocks->adjust('table', 'cy', -60 * self::SECOND);
        $this->clocks->start('table');

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('The player cy cannot take the turn.');
        $this->clocks->pass('table', 'ada', 'cy');
    }

    public function testThePlayerNamedIsOnTheClock(): void
    {
        $this->started(new TimeControl(60 * self::SECOND));

        $this->expectException(ClockNotFound::class);
        $this->clocks->pass('table', 'ada', 'zed');
    }

    public function testOnlyThePlayerHoldingTheTurnPassesIt(): void
    {
        $this->started(new TimeControl(60 * self::SECOND));

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('The player bo does not hold the turn.');
        $this->clocks->pass('table', 'bo');
    }

    public function testNoTurnPassesBeforeTheStart(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('The clock table is not started.');
        $this->clocks->pass('table', 'ada');
    }

    public function testAPauseCountsNoTime(): void
    {
        $this->started(new TimeControl(60 * self::SECOND));
        $this->now->sleep(10);

        $paused = $this->clocks->pause('table');
        self::assertTrue($paused->isPaused());
        self::assertSame(10_000, $paused->turnElapsed);
        self::assertSame($paused, $this->clocks->pause('table'));
        $this->now->sleep(100);
        self::assertSame(50_000, $this->clocks->view('table')->players[0]->remaining);
        self::assertFalse($this->clocks->view('table')->players[0]->running);
        self::assertNull($this->clocks->view('table')->expiresAt);

        $resumed = $this->clocks->resume('table');
        self::assertFalse($resumed->isPaused());
        self::assertSame($resumed, $this->clocks->resume('table'));
        $this->now->sleep(5);

        self::assertSame(45_000, $this->clocks->pass('table', 'ada')->player('ada')?->remaining);
        self::assertEquals(
            [new ClockPaused('table'), new ClockResumed('table'), new TurnEnded('table', 'ada', 15_000), new TurnStarted('table', 'bo')],
            $this->observed->take(),
        );
    }

    public function testATurnPassedWhilePausedStartsPaused(): void
    {
        $this->started(new TimeControl(60 * self::SECOND));
        $this->now->sleep(10);
        $this->clocks->pause('table');
        $this->now->sleep(10);

        $clock = $this->clocks->pass('table', 'ada');

        self::assertSame(50_000, $clock->player('ada')?->remaining);
        self::assertSame('bo', $clock->current);
        self::assertTrue($clock->isPaused());
        self::assertSame(0, $clock->turnElapsed);
        self::assertFalse($clock->expiryTold);
    }

    public function testAClockNotStartedIsNotPaused(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));

        $this->expectException(ClockRefused::class);
        $this->clocks->pause('table');
    }

    public function testAClockNotStartedIsNotResumed(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));

        $this->expectException(ClockRefused::class);
        $this->clocks->resume('table');
    }

    public function testAVacationStopsThePlayersClock(): void
    {
        $this->started(new TimeControl(10 * self::DAY));
        $start = $this->now->now();
        $this->clocks->declareVacation('table', 'bo', new Vacation($start->modify('+1 day'), $start->modify('+3 days')));
        self::assertEquals([new VacationDeclared('table', 'bo')], $this->observed->take());
        $this->now->sleep(2 * self::DAY / 1000);
        $this->clocks->pass('table', 'ada');

        $view = $this->clocks->view('table');
        self::assertTrue($view->players[1]->onVacation);
        self::assertSame('2027-01-14T12:00:00.000+00:00', $view->toArray()['expiresAt']);

        $this->now->sleep(2 * self::DAY / 1000);
        $clock = $this->clocks->pass('table', 'bo');

        self::assertSame(9 * self::DAY, $clock->player('bo')?->remaining);
        self::assertSame(self::DAY, $clock->player('bo')->spent);
    }

    public function testTheMomentOfExpirySkipsTheVacationsToCome(): void
    {
        $this->started(new TimeControl(3 * self::DAY));
        $start = $this->now->now();
        $this->clocks->declareVacation('table', 'ada', new Vacation($start->modify('+1 day'), $start->modify('+2 days')));
        $this->clocks->declareVacation('table', 'ada', new Vacation($start->modify('+5 days'), $start->modify('+6 days')));
        $this->clocks->declareVacation('table', 'ada', new Vacation($start->modify('+2 days'), $start->modify('+3 days')));

        self::assertSame('2027-01-06T12:00:00.000+00:00', $this->clocks->view('table')->toArray()['expiresAt']);
    }

    public function testTheMomentOfExpiryEndsBeforeALaterVacation(): void
    {
        $this->started(new TimeControl(self::DAY));
        $start = $this->now->now();
        $this->clocks->declareVacation('table', 'ada', new Vacation($start->modify('+1 day'), $start->modify('+2 days')));

        self::assertSame('2027-01-02T12:00:00.000+00:00', $this->clocks->view('table')->toArray()['expiresAt']);
    }

    public function testTheMomentOfExpiryIsNowOnceRunOut(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $start = $this->now->now();
        $this->clocks->declareVacation('table', 'ada', new Vacation($start->modify('+1 day'), $start->modify('+2 days')));
        $this->now->sleep(20);

        self::assertSame('2027-01-01T12:00:20.000+00:00', $this->clocks->view('table')->toArray()['expiresAt']);
    }

    public function testAVacationBegunCountsFromNow(): void
    {
        $this->started(new TimeControl(10 * self::DAY));
        $this->now->sleep(self::DAY / 1000);
        $now = $this->now->now();

        $clock = $this->clocks->declareVacation('table', 'ada', new Vacation($now->modify('-12 hours'), $now->modify('+1 day')));

        self::assertSame(self::DAY, $clock->turnElapsed);
        self::assertSame($now->format('Uv'), $clock->runningSince?->format('Uv'));
        self::assertSame(9 * self::DAY, $this->clocks->view('table')->players[0]->remaining);
        self::assertTrue($this->clocks->view('table')->players[0]->onVacation);
    }

    public function testAVacationOfAPausedPlayerKeepsThePause(): void
    {
        $this->started(new TimeControl(10 * self::DAY));
        $this->now->sleep(10);
        $this->clocks->pause('table');
        $now = $this->now->now();

        $clock = $this->clocks->declareVacation('table', 'ada', new Vacation($now, $now->modify('+1 day')));

        self::assertTrue($clock->isPaused());
        self::assertSame(10_000, $clock->turnElapsed);
    }

    public function testAVacationOfAnotherPlayerLeavesTheTurnAsItIs(): void
    {
        $started = $this->started(new TimeControl(10 * self::DAY));
        $this->now->sleep(10);
        $now = $this->now->now();

        $clock = $this->clocks->declareVacation('table', 'bo', new Vacation($now, $now->modify('+1 day')));

        self::assertSame(0, $clock->turnElapsed);
        self::assertEquals($started->runningSince, $clock->runningSince);
    }

    public function testAVacationOverIsRefused(): void
    {
        $this->started(new TimeControl(60 * self::SECOND));
        $now = $this->now->now();

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('A vacation is declared before it is over.');
        $this->clocks->declareVacation('table', 'ada', new Vacation($now->modify('-1 day'), $now));
    }

    public function testTheVacationsOverAreForgotten(): void
    {
        $this->started(new TimeControl(30 * self::DAY));
        $start = $this->now->now();
        for ($day = 1; $day <= 8; ++$day) {
            $this->clocks->declareVacation('table', 'bo', new Vacation($start->modify(\sprintf('+%d days', $day)), $start->modify(\sprintf('+%d days +1 hour', $day))));
        }

        $this->now->sleep(3 * self::DAY / 1000);
        $now = $this->now->now();

        $clock = $this->clocks->declareVacation('table', 'bo', new Vacation($now->modify('+20 days'), $now->modify('+21 days')));

        $bo = $clock->player('bo');
        self::assertInstanceOf(PlayerClock::class, $bo);
        self::assertCount(7, $bo->vacations);
        self::assertSame('2027-01-04 12:00', $bo->vacations[0]->from->format('Y-m-d H:i'));
    }

    public function testAVacationEndingNowIsForgotten(): void
    {
        $this->started(new TimeControl(30 * self::DAY));
        $start = $this->now->now();
        $this->clocks->declareVacation('table', 'bo', new Vacation($start->modify('+1 day'), $start->modify('+2 days')));
        $this->now->sleep(2 * self::DAY / 1000);
        $now = $this->now->now();

        $clock = $this->clocks->declareVacation('table', 'bo', new Vacation($now->modify('+1 day'), $now->modify('+2 days')));

        self::assertCount(1, $clock->player('bo')->vacations ?? []);
    }

    public function testAVacationOfAnUnknownPlayerIsRefused(): void
    {
        $this->started(new TimeControl(60 * self::SECOND));
        $now = $this->now->now();

        $this->expectException(ClockNotFound::class);
        $this->clocks->declareVacation('table', 'zed', new Vacation($now, $now->modify('+1 day')));
    }

    public function testTimeIsAddedAndTakenAway(): void
    {
        $this->clocks->create('table', ['ada', 'bo'], new TimeControl(60 * self::SECOND));

        self::assertSame(70_000, $this->clocks->adjust('table', 'bo', 10 * self::SECOND)->player('bo')?->remaining);
        self::assertSame(-20_000, $this->clocks->adjust('table', 'bo', -90 * self::SECOND)->player('bo')?->remaining);
        self::assertEquals([new TimeAdjusted('table', 'bo', 10_000), new TimeAdjusted('table', 'bo', -90_000)], $this->observed->take());
    }

    public function testAForfeitingClockKeepsBudgetsAtZeroOrMore(): void
    {
        $this->clocks->create('table', ['ada', 'bo'], new TimeControl(60 * self::SECOND, overtime: Overtime::Forfeit));

        self::assertSame(0, $this->clocks->adjust('table', 'bo', -90 * self::SECOND)->player('bo')?->remaining);
        self::assertSame(5_000, $this->clocks->adjust('table', 'bo', 5 * self::SECOND)->player('bo')?->remaining);
    }

    public function testTimeGivenBackInOvertimeLetsTheExpiryBeToldAgain(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->now->sleep(12);
        $this->clocks->check('table');
        $this->observed->take();

        $clock = $this->clocks->adjust('table', 'ada', 5 * self::SECOND);
        self::assertFalse($clock->expiryTold);

        $this->now->sleep(4);
        $this->clocks->check('table');
        self::assertEquals([new TimeAdjusted('table', 'ada', 5_000), new TimeExpired('table', 'ada')], $this->observed->take());
    }

    public function testTimeGivenTooShortInOvertimeKeepsTheExpiryTold(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->now->sleep(12);
        $this->clocks->check('table');

        self::assertTrue($this->clocks->adjust('table', 'ada', 2 * self::SECOND)->expiryTold);
    }

    public function testTimeGivenToAnotherPlayerKeepsTheExpiryTold(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->now->sleep(12);
        $this->clocks->check('table');

        self::assertTrue($this->clocks->adjust('table', 'bo', 20 * self::SECOND)->expiryTold);
    }

    public function testAChangeOfTimeIsNotZeroNorTooLarge(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));
        self::assertSame(60_000 + TimeControl::MAX_BUDGET, $this->clocks->adjust('table', 'ada', TimeControl::MAX_BUDGET)->player('ada')?->remaining);
        foreach ([0, TimeControl::MAX_BUDGET + 1, -TimeControl::MAX_BUDGET - 1] as $delta) {
            try {
                $this->clocks->adjust('table', 'ada', $delta);
                self::fail('Out of bounds.');
            } catch (InvalidClock $exception) {
                self::assertSame('A change of time is not zero, and at most 31 days either way.', $exception->getMessage());
            }
        }
    }

    public function testAChangeOfTimeIsForAPlayerOnTheClock(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));

        $this->expectException(ClockNotFound::class);
        $this->clocks->adjust('table', 'zed', 1_000);
    }

    public function testStoppingChargesTheTurnWithoutEndingIt(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->now->sleep(12);

        $clock = $this->clocks->stop('table');

        self::assertTrue($clock->stopped);
        self::assertSame(-2_000, $clock->player('ada')?->remaining);
        self::assertSame(0, $clock->player('ada')->turns);
        self::assertSame(12_000, $clock->player('ada')->spent);
        self::assertNull($clock->runningSince);
        self::assertSame(0, $clock->turnElapsed);
        self::assertEquals([new TimeExpired('table', 'ada'), new ClockStopped('table')], $this->observed->take());

        $view = $this->clocks->view('table');
        self::assertFalse($view->paused);
        self::assertTrue($view->stopped);
        self::assertNull($view->expiresAt);
        self::assertSame(-2_000, $view->players[0]->remaining);
    }

    public function testStoppingAClockNotStartedChargesNothing(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(10 * self::SECOND));

        $clock = $this->clocks->stop('table');

        self::assertSame(10_000, $clock->player('ada')?->remaining);
        self::assertEquals([new ClockStopped('table')], $this->observed->take());
    }

    public function testAStoppedClockChangesNoMore(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->clocks->stop('table');

        $this->expectException(ClockRefused::class);
        $this->expectExceptionMessageIsOrContains('The clock table is stopped.');
        $this->clocks->adjust('table', 'ada', 1_000);
    }

    public function testAStoppedClockPassesNoTurn(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->clocks->stop('table');

        $this->expectException(ClockRefused::class);
        $this->clocks->pass('table', 'ada');
    }

    public function testTheViewShowsEveryPlayer(): void
    {
        $this->clocks->create('table', ['ada', 'bo'], new TimeControl(60 * self::SECOND, Mode::Fischer, 2 * self::SECOND));
        $this->clocks->start('table');

        $this->now->sleep(10);
        $this->clocks->pass('table', 'ada');
        $this->now->sleep(4.5);

        self::assertSame([
            'clock' => 'table',
            'current' => 'bo',
            'paused' => false,
            'stopped' => false,
            'expiresAt' => '2027-01-01T12:01:10.000+00:00',
            'players' => [
                ['player' => 'ada', 'remaining' => 52_000, 'overtime' => 0, 'turns' => 1, 'spent' => 10_000, 'running' => false, 'out' => false, 'onVacation' => false],
                ['player' => 'bo', 'remaining' => 55_500, 'overtime' => 0, 'turns' => 0, 'spent' => 0, 'running' => true, 'out' => false, 'onVacation' => false],
            ],
            'version' => 3,
        ], $this->clocks->view('table')->toArray());
    }

    public function testTheViewOfAClockNotStarted(): void
    {
        $this->clocks->create('table', ['ada'], new TimeControl(60 * self::SECOND));

        $view = $this->clocks->view('table');

        self::assertNull($view->current);
        self::assertNull($view->expiresAt);
        self::assertFalse($view->paused);
        self::assertFalse($view->players[0]->running);
        self::assertNull($view->toArray()['expiresAt']);
    }

    public function testNoObserverIsNeededToTellTheEvents(): void
    {
        $clocks = new TurnClock(new InMemoryClockStore(), $this->now);
        $clocks->create('table', ['ada', 'bo'], new TimeControl(60 * self::SECOND));
        $clocks->start('table');
        $clocks->pause('table');
        $clocks->resume('table');

        self::assertSame('bo', $clocks->pass('table', 'ada')->current);
    }

    public function testAnExpiryIsNotToldForABudgetAtZero(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->clocks->adjust('table', 'ada', -10 * self::SECOND);
        $this->observed->take();
        $this->now->sleep(1);

        $before = $this->clocks->clock('table');

        self::assertSame($before, $this->clocks->check('table'));
        self::assertSame([], $this->observed->take());
        self::assertFalse($before->expiryTold);
    }

    public function testAnExpiryIsNotToldWhenTheTurnEndsOnABudgetAtZero(): void
    {
        $this->started(new TimeControl(10 * self::SECOND));
        $this->clocks->adjust('table', 'ada', -10 * self::SECOND);
        $this->observed->take();
        $this->now->sleep(1);

        $this->clocks->pass('table', 'ada');

        self::assertEquals([new TurnEnded('table', 'ada', 1_000), new TurnStarted('table', 'bo')], $this->observed->take());
    }

    public function testAForfeitingTurnEndedAtZeroEarnsNoIncrement(): void
    {
        $this->started(new TimeControl(10 * self::SECOND, Mode::Fischer, 5 * self::SECOND, Overtime::Forfeit));
        $this->now->sleep(10);

        $clock = $this->clocks->pass('table', 'ada');

        self::assertSame(0, $clock->player('ada')?->remaining);
    }

    public function testAVacationNotBegunIsNotShown(): void
    {
        $this->started(new TimeControl(60 * self::SECOND));
        $now = $this->now->now();
        $this->clocks->declareVacation('table', 'bo', new Vacation($now->modify('+1 second'), $now->modify('+1 day')));

        self::assertFalse($this->clocks->view('table')->players[1]->onVacation);
        $this->now->sleep(1);
        self::assertTrue($this->clocks->view('table')->players[1]->onVacation);
        $this->now->sleep(self::DAY / 1000 - 1);
        self::assertFalse($this->clocks->view('table')->players[1]->onVacation);
    }

    private function started(TimeControl $control): TableClock
    {
        $this->clocks->create('table', ['ada', 'bo'], $control);
        $clock = $this->clocks->start('table');
        $this->observed->take();

        return $clock;
    }
}
