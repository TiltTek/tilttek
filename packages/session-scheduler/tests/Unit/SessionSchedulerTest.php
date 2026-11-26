<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\SessionScheduler\DateProposal;
use Tilttek\SessionScheduler\Event\AbsenceAnnounced;
use Tilttek\SessionScheduler\Event\AttendanceConfirmed;
use Tilttek\SessionScheduler\Event\DatesProposed;
use Tilttek\SessionScheduler\Event\GameMasterAbsent;
use Tilttek\SessionScheduler\Event\QuorumLost;
use Tilttek\SessionScheduler\Event\QuorumRestored;
use Tilttek\SessionScheduler\Event\SeriesChanged;
use Tilttek\SessionScheduler\Event\SeriesCreated;
use Tilttek\SessionScheduler\Event\SessionCancelled;
use Tilttek\SessionScheduler\Event\SessionHeld;
use Tilttek\SessionScheduler\Event\SessionPlanned;
use Tilttek\SessionScheduler\Event\SessionRescheduled;
use Tilttek\SessionScheduler\Event\SubstituteAssigned;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Exception\ScheduleNotFound;
use Tilttek\SessionScheduler\Exception\ScheduleRefused;
use Tilttek\SessionScheduler\Reminder;
use Tilttek\SessionScheduler\Reply;
use Tilttek\SessionScheduler\Series;
use Tilttek\SessionScheduler\Session;
use Tilttek\SessionScheduler\SessionQuery;
use Tilttek\SessionScheduler\SessionScheduler;
use Tilttek\SessionScheduler\SessionStatus;
use Tilttek\SessionScheduler\Store\InMemorySessionStore;
use Tilttek\SessionScheduler\Tests\Unit\Fixture\CountingStore;
use Tilttek\SessionScheduler\Tests\Unit\Fixture\Observed;

#[CoversClass(SessionScheduler::class)]
#[CoversClass(Reminder::class)]
#[CoversClass(ScheduleNotFound::class)]
#[CoversClass(ScheduleRefused::class)]
#[CoversClass(SeriesCreated::class)]
#[CoversClass(SeriesChanged::class)]
#[CoversClass(DatesProposed::class)]
#[CoversClass(SessionPlanned::class)]
#[CoversClass(SessionRescheduled::class)]
#[CoversClass(SessionCancelled::class)]
#[CoversClass(SessionHeld::class)]
#[CoversClass(AttendanceConfirmed::class)]
#[CoversClass(AbsenceAnnounced::class)]
#[CoversClass(GameMasterAbsent::class)]
#[CoversClass(SubstituteAssigned::class)]
#[CoversClass(QuorumLost::class)]
#[CoversClass(QuorumRestored::class)]
final class SessionSchedulerTest extends TestCase
{
    private const string FIRST = 's1:20270306T000000Z';

    private const string SECOND = 's1:20270313T000000Z';

    private const string THIRD = 's1:20270319T230000Z';

    private InMemorySessionStore $store;

    private MockClock $clock;

    private Observed $observed;

    private SessionScheduler $scheduler;

    protected function setUp(): void
    {
        $this->store = new InMemorySessionStore();
        $this->clock = new MockClock(new DateTimeImmutable('2027-03-01 12:00', $this->zone()));
        $this->observed = new Observed();
        $this->scheduler = new SessionScheduler($this->store, $this->clock, $this->observed, $this->observed);
    }

    public function testASeriesIsCreated(): void
    {
        $series = $this->scheduler->createSeries($this->series());

        self::assertSame(1, $series->version);
        self::assertSame(1, $this->scheduler->series('s1')->version);
        self::assertEquals([new SeriesCreated('s1')], $this->observed->take());
    }

    public function testANewSeriesIsAtVersionZero(): void
    {
        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A new series is at version 0.');

        $this->scheduler->createSeries($this->series()->withVersion(1));
    }

    public function testASeriesIsCreatedOnce(): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A series has the identifier s1.');

        $this->scheduler->createSeries($this->series());
    }

    public function testTheGameMasterChangesTheSeriesAndMayHandItOver(): void
    {
        $kept = $this->scheduler->createSeries($this->series());
        $this->observed->take();

        $changed = $this->scheduler->changeSeries($this->series(gameMaster: 'gm2', version: $kept->version), 'gm');

        self::assertSame(['gm2', 2], [$changed->gameMaster, $changed->version]);
        self::assertEquals([new SeriesChanged('s1')], $this->observed->take());
        self::assertSame(3, $this->scheduler->changeSeries($this->series(gameMaster: 'gm2', version: 2), 'gm2')->version);
    }

    public function testOnlyTheGameMasterChangesTheSeries(): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the game master of the series changes it and its sessions.');

        $this->scheduler->changeSeries($this->series(version: 1), 'p1');
    }

    public function testASeriesStaysInItsCampaign(): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A series stays in its campaign.');

        $this->scheduler->changeSeries($this->series(campaign: 'c2', version: 1), 'gm');
    }

    public function testAMissingSeriesOrSessionIsNotFound(): void
    {
        try {
            $this->scheduler->series('nope');
            self::fail('A missing series is found.');
        } catch (ScheduleNotFound $scheduleNotFound) {
            self::assertSame('No series has the identifier nope.', $scheduleNotFound->getMessage());
        }

        $this->expectException(ScheduleNotFound::class);
        $this->expectExceptionMessageIsOrContains('No session has the identifier nope.');

        $this->scheduler->session('nope');
    }

    public function testAnIdentifierIsCheckedBeforeReading(): void
    {
        $this->expectException(InvalidSchedule::class);

        $this->scheduler->session('no pe');
    }

    public function testTheOccurrencesArePlannedOnceOnTheWallClock(): void
    {
        $this->scheduler->createSeries($this->series());
        $this->observed->take();

        $planned = $this->scheduler->plan('s1', $this->at('2027-03-20 00:00'), 'gm');

        self::assertSame([self::FIRST, self::SECOND, self::THIRD], $this->ids($planned));
        self::assertSame('2027-03-19 19:00 EDT', $planned[2]->startsAt->format('Y-m-d H:i T'));
        self::assertSame(180, $planned[0]->minutes);
        self::assertSame(1, $planned[0]->version);
        self::assertFalse($planned[0]->offCalendar);
        self::assertFalse($planned[0]->isRescheduled());
        self::assertEquals([new SessionPlanned('s1', self::FIRST, false), new SessionPlanned('s1', self::SECOND, false), new SessionPlanned('s1', self::THIRD, false)], $this->observed->take());

        self::assertSame([], $this->scheduler->plan('s1', $this->at('2027-03-20 00:00'), 'gm'));
        self::assertSame([], $this->observed->take());
        self::assertSame(['s1:20270326T230000Z'], $this->ids($this->scheduler->plan('s1', $this->at('2027-03-27 00:00'), 'gm')));
    }

    public function testAPlanIsBoundedAndStartsNow(): void
    {
        $this->scheduler->createSeries($this->series());
        $this->clock->modify('2027-03-05 19:00');

        self::assertSame([self::FIRST], $this->ids($this->scheduler->plan('s1', $this->at('2027-12-31'), 'gm', 1)));
        self::assertSame([self::SECOND, self::THIRD], $this->ids($this->scheduler->plan('s1', $this->at('2027-03-20'), 'gm', 3)));
        self::assertSame(['s1:20270326T230000Z', 's1:20270402T230000Z'], $this->ids($this->scheduler->plan('s1', $this->at('2027-12-31'), 'gm', 2)));
        self::assertCount(100, $this->scheduler->plan('s1', $this->at('2030-12-31'), 'gm', 100));
        self::assertCount(100, $this->scheduler->plan('s1', $this->at('2050-12-31'), 'gm', 100));

        $this->clock->modify('2027-03-05 19:00:01');
        self::assertSame([], $this->ids($this->scheduler->plan('s1', $this->at('2027-03-13 00:00'), 'gm')));
    }

    public function testAPastOccurrenceIsNotPlanned(): void
    {
        $this->scheduler->createSeries($this->series());
        $this->clock->modify('2027-03-05 19:00:01');

        self::assertSame([self::SECOND], $this->ids($this->scheduler->plan('s1', $this->at('2027-03-13 00:00'), 'gm')));
    }

    public function testACancelledOccurrenceIsNotPlannedAgain(): void
    {
        $this->planned();
        $this->scheduler->cancel(self::FIRST, 'gm');

        self::assertSame([], $this->scheduler->plan('s1', $this->at('2027-03-20 00:00'), 'gm'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function planLimits(): iterable
    {
        yield 'none' => [0];
        yield 'too many' => [101];
    }

    #[DataProvider('planLimits')]
    public function testAPlanLimitIsBounded(int $limit): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('A plan is 1 to 100.');

        $this->scheduler->plan('s1', $this->at('2027-12-31'), 'gm', $limit);
    }

    public function testOnlyTheGameMasterPlans(): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->plan('s1', $this->at('2027-12-31'), 'p1');
    }

    public function testASessionOffTheRuleIsAddedAndMarked(): void
    {
        $this->scheduler->createSeries($this->series());
        $this->observed->take();

        $added = $this->scheduler->add('s1', $this->at('2027-03-06 13:00'), 'gm');

        self::assertSame('s1:x20270306T180000Z', $added->id);
        self::assertTrue($added->offCalendar);
        self::assertNull($added->occurrence);
        self::assertSame(1, $added->version);
        self::assertSame(180, $added->minutes);
        self::assertEquals([new SessionPlanned('s1', 's1:x20270306T180000Z', true)], $this->observed->take());
    }

    public function testASessionOnTheRuleIsAddedAsItsOccurrence(): void
    {
        $this->scheduler->createSeries($this->series());

        $added = $this->scheduler->add('s1', $this->at('2027-03-12 19:00'), 'gm');

        self::assertSame(self::SECOND, $added->id);
        self::assertFalse($added->offCalendar);
        self::assertSame('2027-03-12 19:00', $added->occurrence?->format('Y-m-d H:i'));
        self::assertSame([self::FIRST], $this->ids($this->scheduler->plan('s1', $this->at('2027-03-13 00:00'), 'gm')));
    }

    public function testASessionIsAddedOnce(): void
    {
        $this->scheduler->createSeries($this->series());
        $this->scheduler->add('s1', $this->at('2027-03-06 13:00'), 'gm');

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A session of the series stands for that start already.');

        $this->scheduler->add('s1', $this->at('2027-03-06 13:00'), 'gm');
    }

    public function testASessionIsAddedAfterNow(): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A session starts after now.');

        $this->scheduler->add('s1', $this->at('2027-03-01 12:00'), 'gm');
    }

    public function testOnlyTheGameMasterAdds(): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->add('s1', $this->at('2027-03-06 13:00'), 'p1');
    }

    public function testARescheduledSessionDropsItsReplies(): void
    {
        $this->planned();
        $this->scheduler->confirm(self::FIRST, 'p1');
        $this->observed->take();

        $moved = $this->scheduler->reschedule(self::FIRST, $this->at('2027-03-06 19:00'), 'gm');

        self::assertSame('2027-03-06 19:00', $moved->startsAt->format('Y-m-d H:i'));
        self::assertSame('2027-03-05 19:00', $moved->occurrence?->format('Y-m-d H:i'));
        self::assertSame([], $moved->replies);
        self::assertTrue($moved->offCalendar);
        self::assertTrue($moved->isRescheduled());
        self::assertSame(3, $moved->version);
        self::assertEquals([new SessionRescheduled('s1', self::FIRST, true)], $this->observed->take());

        $back = $this->scheduler->reschedule(self::FIRST, $this->at('2027-03-05 19:00'), 'gm');
        self::assertFalse($back->offCalendar);
        self::assertEquals([new SessionRescheduled('s1', self::FIRST, false)], $this->observed->take());
    }

    public function testASessionRescheduledToItsStartIsUnchanged(): void
    {
        $this->planned();

        $same = $this->scheduler->reschedule(self::FIRST, new DateTimeImmutable('2027-03-06 00:00', new DateTimeZone('UTC')), 'gm');

        self::assertSame(1, $same->version);
        self::assertSame([], $this->observed->take());
    }

    public function testASessionIsRescheduledAfterNow(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A session starts after now.');

        $this->scheduler->reschedule(self::FIRST, $this->at('2027-03-01 11:00'), 'gm');
    }

    public function testACancelledSessionNoLongerChanges(): void
    {
        $this->planned();
        $this->scheduler->cancel(self::FIRST, 'gm');

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('Only a planned session changes.');

        $this->scheduler->reschedule(self::FIRST, $this->at('2027-03-06 19:00'), 'gm');
    }

    public function testAStartedSessionNoLongerChanges(): void
    {
        $this->planned();
        $this->clock->modify('2027-03-05 19:00');

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A session that has started no longer changes.');

        $this->scheduler->cancel(self::FIRST, 'gm');
    }

    public function testASessionChangesUntilItStarts(): void
    {
        $this->planned();
        $this->clock->modify('2027-03-05 18:59:59');

        self::assertSame(SessionStatus::Cancelled, $this->scheduler->cancel(self::FIRST, 'gm')->status);
    }

    public function testOnlyTheGameMasterReschedules(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->reschedule(self::FIRST, $this->at('2027-03-06 19:00'), 'p1');
    }

    public function testAPostponementMovesTheLaterSessionsAndKeepsTheirSpacing(): void
    {
        $this->planned();
        $this->scheduler->confirm(self::SECOND, 'p1');
        $this->observed->take();

        $moved = $this->scheduler->postpone(self::SECOND, 7, 'gm');

        self::assertSame([self::SECOND, self::THIRD], $this->ids($moved));
        self::assertSame(['2027-03-19 19:00 EDT', '2027-03-26 19:00 EDT'], array_map(static fn (Session $session): string => $session->startsAt->format('Y-m-d H:i T'), $moved));
        self::assertSame([], $moved[0]->replies);
        self::assertFalse($moved[0]->offCalendar);
        self::assertSame('2027-03-05 19:00', $this->scheduler->session(self::FIRST)->startsAt->format('Y-m-d H:i'));
        self::assertEquals([new SessionRescheduled('s1', self::SECOND, false), new SessionRescheduled('s1', self::THIRD, false)], $this->observed->take());
    }

    public function testAPostponementOffTheRuleMarksTheSessions(): void
    {
        $this->planned();
        $this->scheduler->cancel(self::SECOND, 'gm');

        $moved = $this->scheduler->postpone(self::FIRST, 1, 'gm');

        self::assertSame([self::FIRST, self::THIRD], $this->ids($moved));
        self::assertTrue($moved[0]->offCalendar);
        self::assertSame('2027-03-20 19:00', $moved[1]->startsAt->format('Y-m-d H:i'));
        self::assertSame('2028-03-06 19:00', $this->scheduler->postpone(self::FIRST, 366, 'gm')[0]->startsAt->format('Y-m-d H:i'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function postponements(): iterable
    {
        yield 'none' => [0];
        yield 'too long' => [367];
    }

    #[DataProvider('postponements')]
    public function testAPostponementIsBounded(int $days): void
    {
        $this->planned();

        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('A postponement in days is 1 to 366.');

        $this->scheduler->postpone(self::FIRST, $days, 'gm');
    }

    public function testOnlyTheGameMasterPostpones(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->postpone(self::FIRST, 7, 'p1');
    }

    public function testAPostponementMovesAtMostAHundredSessions(): void
    {
        $this->scheduler->createSeries($this->series(rule: 'FREQ=DAILY'));
        $this->scheduler->plan('s1', $this->at('2027-12-31'), 'gm', 100);
        $this->scheduler->plan('s1', $this->at('2027-12-31'), 'gm', 100);

        self::assertCount(100, $this->scheduler->postpone(self::FIRST, 1, 'gm'));
    }

    public function testASessionIsCancelled(): void
    {
        $this->planned();

        $cancelled = $this->scheduler->cancel(self::FIRST, 'gm');

        self::assertSame(SessionStatus::Cancelled, $cancelled->status);
        self::assertEquals([new SessionCancelled('s1', self::FIRST)], $this->observed->take());
    }

    public function testOnlyTheGameMasterCancels(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->cancel(self::FIRST, 'p1');
    }

    public function testASessionIsHeldOnceStartedByAGameMasterOfIt(): void
    {
        $this->planned();
        $this->scheduler->substitute(self::SECOND, 'sub', 'gm');
        $this->observed->take();
        $this->clock->modify('2027-03-05 19:00');

        self::assertSame(SessionStatus::Held, $this->scheduler->hold(self::FIRST, 'gm')->status);
        self::assertEquals([new SessionHeld('s1', self::FIRST)], $this->observed->take());

        $this->clock->modify('2027-03-12 19:00');
        self::assertSame(SessionStatus::Held, $this->scheduler->hold(self::SECOND, 'sub')->status);
    }

    public function testASessionIsNotHeldBeforeItStarts(): void
    {
        $this->planned();
        $this->clock->modify('2027-03-05 18:59:59');

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A session is held once it has started.');

        $this->scheduler->hold(self::FIRST, 'gm');
    }

    public function testAPlayerDoesNotHoldASession(): void
    {
        $this->planned();
        $this->clock->modify('2027-03-05 19:00');

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('Only a game master of the session marks it held.');

        $this->scheduler->hold(self::FIRST, 'p1');
    }

    public function testACancelledSessionIsNotHeld(): void
    {
        $this->planned();
        $this->scheduler->cancel(self::FIRST, 'gm');
        $this->clock->modify('2027-03-05 19:00');

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('Only a planned session is held.');

        $this->scheduler->hold(self::FIRST, 'gm');
    }

    public function testASubstituteRunsASessionUntilTheGameMasterTakesItBack(): void
    {
        $this->planned();

        self::assertSame('sub', $this->scheduler->substitute(self::FIRST, 'sub', 'gm')->gameMaster);
        self::assertSame(2, $this->scheduler->substitute(self::FIRST, 'sub', 'gm')->version);
        self::assertNull($this->scheduler->substitute(self::FIRST, 'gm', 'gm')->gameMaster);
        self::assertSame(3, $this->scheduler->substitute(self::FIRST, null, 'gm')->version);
        self::assertEquals([new SubstituteAssigned('s1', self::FIRST, 'sub'), new SubstituteAssigned('s1', self::FIRST, null)], $this->observed->take());
    }

    public function testAPlayerIsNotASubstitute(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A substitute game master is not one of the players.');

        $this->scheduler->substitute(self::FIRST, 'p1', 'gm');
    }

    public function testASubstituteIsAnIdentifier(): void
    {
        $this->planned();

        $this->expectException(InvalidSchedule::class);

        $this->scheduler->substitute(self::FIRST, 's u b', 'gm');
    }

    public function testOnlyTheGameMasterGivesASubstitute(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->substitute(self::FIRST, 'sub', 'p1');
    }

    public function testAPlayerSaysTheyAreComingOnce(): void
    {
        $this->planned();

        $session = $this->scheduler->confirm(self::FIRST, 'p1');

        self::assertSame(Reply::Coming, $session->reply('p1'));
        self::assertEquals([new AttendanceConfirmed('s1', self::FIRST, 'p1')], $this->observed->take());
        self::assertSame(2, $this->scheduler->confirm(self::FIRST, 'p1')->version);
        self::assertSame([], $this->observed->take());
        self::assertSame(Reply::Coming, $this->scheduler->confirm(self::FIRST, 'gm')->reply('gm'));
    }

    public function testAStrangerDoesNotAnswer(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('Only a player of the series or the game master of the session answers for it.');

        $this->scheduler->confirm(self::FIRST, 'p9');
    }

    public function testTheSubstituteAnswersInPlaceOfTheGameMaster(): void
    {
        $this->planned();
        $this->scheduler->substitute(self::FIRST, 'sub', 'gm');

        self::assertSame(Reply::Coming, $this->scheduler->confirm(self::FIRST, 'sub')->reply('sub'));

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->absent(self::FIRST, 'gm');
    }

    public function testAMemberComesToAtMostSoManyUpcomingSessions(): void
    {
        $this->planned();
        $scheduler = new SessionScheduler($this->store, $this->clock, maxComing: 1);
        $scheduler->confirm(self::FIRST, 'p1');

        self::assertSame(2, $scheduler->confirm(self::FIRST, 'p1')->version);
        self::assertSame(Reply::Coming, $scheduler->confirm(self::SECOND, 'p2')->reply('p2'));

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A member comes to at most 1 upcoming sessions.');

        $scheduler->confirm(self::SECOND, 'p1');
    }

    public function testTheCapOfUpcomingSessionsCountsFromNow(): void
    {
        $this->planned();
        $scheduler = new SessionScheduler($this->store, $this->clock, maxComing: 1);
        $scheduler->confirm(self::FIRST, 'p1');

        $this->clock->modify('2027-03-05 19:00');

        self::assertSame(Reply::Coming, $scheduler->confirm(self::SECOND, 'p1')->reply('p1'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function caps(): iterable
    {
        yield 'none' => [0];
        yield 'too many' => [101];
    }

    #[DataProvider('caps')]
    public function testTheCapIsBounded(int $cap): void
    {
        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('The cap of upcoming sessions per member is 1 to 100.');

        new SessionScheduler($this->store, $this->clock, maxComing: $cap);
    }

    public function testTheCapAcceptsAHundred(): void
    {
        $this->planned();

        self::assertSame(Reply::Coming, new SessionScheduler($this->store, $this->clock, maxComing: 100)->confirm(self::FIRST, 'p1')->reply('p1'));
    }

    public function testAnAbsenceWithNoticeAndTheQuorum(): void
    {
        $this->planned();

        $session = $this->scheduler->absent(self::FIRST, 'p1');
        self::assertSame(Reply::Absent, $session->reply('p1'));
        self::assertEquals([new AbsenceAnnounced('s1', self::FIRST, 'p1', false)], $this->observed->take());

        self::assertSame(2, $this->scheduler->absent(self::FIRST, 'p1')->version);
        self::assertSame([], $this->observed->take());

        $this->scheduler->absent(self::FIRST, 'p2');
        self::assertEquals([new AbsenceAnnounced('s1', self::FIRST, 'p2', false), new QuorumLost('s1', self::FIRST)], $this->observed->take());

        $this->scheduler->absent(self::FIRST, 'p3');
        self::assertEquals([new AbsenceAnnounced('s1', self::FIRST, 'p3', false)], $this->observed->take());

        $this->scheduler->confirm(self::FIRST, 'p3');
        self::assertEquals([new AttendanceConfirmed('s1', self::FIRST, 'p3')], $this->observed->take());

        $this->scheduler->confirm(self::FIRST, 'p2');
        self::assertEquals([new AttendanceConfirmed('s1', self::FIRST, 'p2'), new QuorumRestored('s1', self::FIRST)], $this->observed->take());

        $this->scheduler->confirm(self::FIRST, 'p1');
        self::assertEquals([new AttendanceConfirmed('s1', self::FIRST, 'p1')], $this->observed->take());
    }

    public function testAnAbsenceLaterThanTheNoticeIsMarkedLate(): void
    {
        $this->planned();
        $this->clock->modify('2027-03-04 19:00');

        self::assertSame(Reply::Absent, $this->scheduler->absent(self::FIRST, 'p1')->reply('p1'));

        $this->clock->modify('2027-03-04 19:00:01');
        self::assertSame(Reply::AbsentLate, $this->scheduler->absent(self::FIRST, 'p2')->reply('p2'));
        self::assertEquals([new AbsenceAnnounced('s1', self::FIRST, 'p1', false), new AbsenceAnnounced('s1', self::FIRST, 'p2', true), new QuorumLost('s1', self::FIRST)], $this->observed->take());
    }

    public function testTheGameMasterWhoRunsASessionSaysTheyAreAbsent(): void
    {
        $this->planned();
        $this->scheduler->absent(self::FIRST, 'p1');
        $this->scheduler->absent(self::FIRST, 'p2');

        $this->observed->take();
        $this->clock->modify('2027-03-05 18:00');

        self::assertSame(Reply::AbsentLate, $this->scheduler->absent(self::FIRST, 'gm')->reply('gm'));
        self::assertEquals([new GameMasterAbsent('s1', self::FIRST, 'gm', true)], $this->observed->take());

        $this->scheduler->substitute(self::SECOND, 'sub', 'gm');
        $this->observed->take();
        $this->scheduler->absent(self::SECOND, 'sub');
        self::assertEquals([new GameMasterAbsent('s1', self::SECOND, 'sub', false)], $this->observed->take());
    }

    public function testDatesAreProposedForANewSession(): void
    {
        $this->scheduler->createSeries($this->series());
        $this->observed->take();

        $proposal = $this->scheduler->propose('s1', [$this->at('2027-03-03 19:00'), $this->at('2027-03-02 19:00')], 'gm');

        self::assertSame(['s1', null, ['gm', 'p1', 'p2', 'p3'], 180], [$proposal->series, $proposal->session, $proposal->members, $proposal->minutes]);
        self::assertSame(['2027-03-02', '2027-03-03'], $this->days($proposal));
        self::assertSame([$proposal], $this->observed->proposals);
        self::assertEquals([new DatesProposed('s1', null)], $this->observed->take());
        self::assertSame('s1', new SessionScheduler($this->store, $this->clock)->propose('s1', [$this->at('2027-03-02 19:00')], 'gm')->series);
    }

    public function testAProposedDateIsAfterNow(): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A session starts after now.');

        $this->scheduler->propose('s1', [$this->at('2027-03-02 19:00'), $this->at('2027-03-01 12:00')], 'gm');
    }

    public function testOnlyTheGameMasterProposes(): void
    {
        $this->scheduler->createSeries($this->series());

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->propose('s1', [$this->at('2027-03-02 19:00')], 'p1');
    }

    public function testFreeDaysAreProposedToReplaceASession(): void
    {
        $this->planned();
        $this->scheduler->cancel(self::THIRD, 'gm');
        $this->scheduler->substitute(self::FIRST, 'sub', 'gm');

        $this->observed->take();

        $proposal = $this->scheduler->proposeReplacement(self::FIRST, $this->at('2027-03-19 19:00'), 'gm', 20);

        self::assertSame(['s1', self::FIRST, ['sub', 'p1', 'p2', 'p3'], 180], [$proposal->series, $proposal->session, $proposal->members, $proposal->minutes]);
        self::assertSame(['2027-03-02', '2027-03-03', '2027-03-04', '2027-03-05', '2027-03-06', '2027-03-07', '2027-03-08', '2027-03-09', '2027-03-10', '2027-03-11', '2027-03-13', '2027-03-14', '2027-03-15', '2027-03-16', '2027-03-17', '2027-03-18', '2027-03-19'], $this->days($proposal));
        self::assertSame('19:00 EDT', $proposal->candidates[13]->format('H:i T'));
        self::assertSame([$proposal], $this->observed->proposals);
        self::assertEquals([new DatesProposed('s1', self::FIRST)], $this->observed->take());

        self::assertSame(['2027-03-02', '2027-03-03', '2027-03-04'], $this->days($this->scheduler->proposeReplacement(self::THIRD, $this->at('2027-12-31'), 'gm', 3)));
        self::assertCount(7, $this->scheduler->proposeReplacement(self::THIRD, $this->at('2027-12-31'), 'gm')->candidates);
        self::assertSame(['2027-03-02'], $this->days($this->scheduler->proposeReplacement(self::THIRD, $this->at('2027-03-02 19:00'), 'gm')));
    }

    public function testAReplacementReadsTheDaysOnTheSeriesWallClock(): void
    {
        $this->planned();
        // 22:00 on the 2nd in Toronto, the 3rd in UTC: the 2nd is taken, the 3rd is free.
        $this->scheduler->add('s1', new DateTimeImmutable('2027-03-03 03:00', new DateTimeZone('UTC')), 'gm');
        $scheduler = new SessionScheduler($this->store, new MockClock(new DateTimeImmutable('2027-03-02 03:00', new DateTimeZone('UTC'))));

        $proposal = $scheduler->proposeReplacement(self::FIRST, $this->at('2027-03-04 19:00'), 'gm');

        self::assertSame(['2027-03-03', '2027-03-04'], $this->days($proposal));
        self::assertSame('2027-03-03 19:00 EST', $proposal->candidates[0]->format('Y-m-d H:i T'));
    }

    public function testAReplacementLooksAYearAhead(): void
    {
        // Every day from 2027-03-05 through 2028-03-05 holds a session; 2028-03-06 is the 367th day after tomorrow.
        $this->scheduler->createSeries($this->series(rule: 'FREQ=DAILY;COUNT=367'));

        for ($call = 0; $call < 4; ++$call) {
            $this->scheduler->plan('s1', $this->at('2029-01-01'), 'gm');
        }

        $this->clock->modify('2027-03-05 12:00');

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('No day is free for the series before the end of the window.');

        $this->scheduler->proposeReplacement(self::FIRST, $this->at('2029-01-01'), 'gm');
    }

    public function testAReplacementFindsTheLastDayOfTheYearAhead(): void
    {
        $this->scheduler->createSeries($this->series(rule: 'FREQ=DAILY;COUNT=366'));

        for ($call = 0; $call < 4; ++$call) {
            $this->scheduler->plan('s1', $this->at('2029-01-01'), 'gm');
        }

        $this->clock->modify('2027-03-05 12:00');

        self::assertSame(['2028-03-05'], $this->days($this->scheduler->proposeReplacement(self::FIRST, $this->at('2029-01-01'), 'gm')));
    }

    public function testAReplacementNeedsAFreeDay(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('No day is free for the series before the end of the window.');

        $this->scheduler->proposeReplacement(self::FIRST, $this->at('2027-03-02 18:59'), 'gm');
    }

    public function testAHeldSessionIsNotReplaced(): void
    {
        $this->planned();
        $this->clock->modify('2027-03-05 19:00');
        $this->scheduler->hold(self::FIRST, 'gm');

        $this->expectException(ScheduleRefused::class);
        $this->expectExceptionMessageIsOrContains('A session held is not replaced.');

        $this->scheduler->proposeReplacement(self::FIRST, $this->at('2027-12-31'), 'gm');
    }

    public function testOnlyTheGameMasterProposesAReplacement(): void
    {
        $this->planned();

        $this->expectException(ScheduleRefused::class);

        $this->scheduler->proposeReplacement(self::FIRST, $this->at('2027-12-31'), 'p1');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function counts(): iterable
    {
        yield 'none' => [0];
        yield 'too many' => [21];
    }

    #[DataProvider('counts')]
    public function testAReplacementCountIsBounded(int $count): void
    {
        $this->planned();

        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('A proposal is 1 to 20.');

        $this->scheduler->proposeReplacement(self::FIRST, $this->at('2027-12-31'), 'gm', $count);
    }

    public function testRemindersAreDueOnceInTheirWindow(): void
    {
        $this->planned(reminder: 6);
        $this->scheduler->absent(self::FIRST, 'p2');
        $this->scheduler->substitute(self::FIRST, 'sub', 'gm');
        $this->scheduler->cancel(self::THIRD, 'gm');

        $reminders = $this->scheduler->reminders($this->at('2027-03-05 12:00'), $this->at('2027-03-05 13:00'));

        self::assertCount(1, $reminders);
        self::assertSame(['s1', self::FIRST, ['sub', 'p1', 'p3']], [$reminders[0]->series, $reminders[0]->session, $reminders[0]->members]);
        self::assertSame('2027-03-05 19:00', $reminders[0]->startsAt->format('Y-m-d H:i'));
        self::assertSame('2027-03-05 13:00', $reminders[0]->dueAt->format('Y-m-d H:i'));
        self::assertSame([], $this->scheduler->reminders($this->at('2027-03-05 13:00'), $this->at('2027-03-05 14:00')));
        self::assertSame([], $this->scheduler->reminders($this->at('2027-03-05 12:00'), $this->at('2027-03-05 12:59:59')));
        self::assertSame([], $this->scheduler->reminders($this->at('2027-03-05 13:00'), $this->at('2027-03-05 13:00')));
        self::assertSame([self::FIRST, self::SECOND], array_map(static fn (Reminder $reminder): string => $reminder->session, $this->scheduler->reminders($this->at('2027-03-01 00:00'), $this->at('2027-04-01 00:00'))));
        self::assertSame(['gm', 'p1', 'p2', 'p3'], $this->scheduler->reminders($this->at('2027-03-12 12:00'), $this->at('2027-03-12 13:00'))[0]->members);
    }

    public function testRemindersReachAsFarAsTheirEarliestHours(): void
    {
        $this->planned(reminder: 168);

        self::assertSame([self::SECOND], array_map(static fn (Reminder $reminder): string => $reminder->session, $this->scheduler->reminders($this->at('2027-03-05 18:00'), $this->at('2027-03-05 19:00'))));
    }

    public function testASeriesWithoutReminderHasNone(): void
    {
        $this->planned();

        self::assertSame([], $this->scheduler->reminders($this->at('2027-03-01 00:00'), $this->at('2027-03-31 00:00')));
    }

    public function testRemindersOfSeveralSeriesAreInOrder(): void
    {
        $this->planned(reminder: 6);
        $this->scheduler->createSeries(new Series('s2', 'c2', 'gm', $this->at('2027-03-05 18:00'), RecurrenceRule::parse('FREQ=WEEKLY'), [], reminder: 1));
        $this->scheduler->plan('s2', $this->at('2027-03-06'), 'gm');

        self::assertSame([self::FIRST, 's2:20270305T230000Z'], array_map(static fn (Reminder $reminder): string => $reminder->session, $this->scheduler->reminders($this->at('2027-03-05 00:00'), $this->at('2027-03-06 00:00'))));
    }

    public function testRemindersDueTogetherAreOrderedBySession(): void
    {
        $this->scheduler->createSeries(new Series('z1', 'c1', 'gm', $this->at('2027-03-05 19:00'), RecurrenceRule::parse('FREQ=WEEKLY'), [], reminder: 1));
        $this->scheduler->createSeries(new Series('a1', 'c2', 'gm', $this->at('2027-03-05 20:00'), RecurrenceRule::parse('FREQ=WEEKLY'), [], reminder: 2));
        $this->scheduler->plan('z1', $this->at('2027-03-06'), 'gm');
        $this->scheduler->plan('a1', $this->at('2027-03-06'), 'gm');

        self::assertSame(['a1:20270306T010000Z', 'z1:20270306T000000Z'], array_map(static fn (Reminder $reminder): string => $reminder->session, $this->scheduler->reminders($this->at('2027-03-05 17:00'), $this->at('2027-03-05 18:00'))));
    }

    public function testRemindersReadEachSeriesOnceAndSkipASeriesGone(): void
    {
        $this->planned(reminder: 6);
        $this->store->save(new Session('ghost:1', 'ghost', $this->at('2027-03-05 19:30'), 60));
        $store = new CountingStore($this->store);

        $reminders = new SessionScheduler($store, $this->clock)->reminders($this->at('2027-03-01 00:00'), $this->at('2027-03-31 00:00'));

        self::assertSame([self::FIRST, self::SECOND, self::THIRD], array_map(static fn (Reminder $reminder): string => $reminder->session, $reminders));
        self::assertSame(['s1' => 1, 'ghost' => 1], $store->seriesRead);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function reminderWindows(): iterable
    {
        yield 'backwards' => ['2027-03-05 12:00', '2027-03-05 11:59:59'];
        yield 'too long' => ['2027-03-01 00:00', '2027-04-01 00:00:01'];
    }

    #[DataProvider('reminderWindows')]
    public function testAReminderWindowIsBounded(string $after, string $through): void
    {
        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('A window of reminders ends after it starts, at most 31 days later.');

        $this->scheduler->reminders($this->at($after), $this->at($through));
    }

    public function testSessionsAreReadInBounds(): void
    {
        $this->planned();

        self::assertSame([self::FIRST, self::SECOND, self::THIRD], $this->ids($this->scheduler->sessions(SessionQuery::ofSeries('s1'))));
        self::assertSame([self::FIRST], $this->ids($this->scheduler->sessions(SessionQuery::ofSeries('s1'), 1)));

        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('A read of sessions is 1 to 1000.');

        $this->scheduler->sessions(SessionQuery::ofSeries('s1'), 1001);
    }

    public function testAReadTakesOneSessionAtLeast(): void
    {
        $this->expectException(InvalidSchedule::class);

        $this->scheduler->sessions(new SessionQuery(), 0);
    }

    public function testTheSchedulerWorksWithoutObserver(): void
    {
        $scheduler = new SessionScheduler($this->store, $this->clock);
        $scheduler->createSeries($this->series());

        self::assertCount(1, $scheduler->plan('s1', $this->at('2027-03-06'), 'gm'));
        self::assertSame(Reply::Absent, $scheduler->absent(self::FIRST, 'p1')->reply('p1'));
        self::assertSame([], $this->observed->events);
    }

    private function planned(?int $reminder = null): void
    {
        $this->scheduler->createSeries($this->series(reminder: $reminder));
        $this->scheduler->plan('s1', $this->at('2027-03-20 00:00'), 'gm');

        $this->observed->take();
    }

    private function series(string $campaign = 'c1', string $gameMaster = 'gm', string $rule = 'FREQ=WEEKLY', ?int $reminder = null, int $version = 0): Series
    {
        return new Series('s1', $campaign, $gameMaster, $this->at('2027-03-05 19:00'), RecurrenceRule::parse($rule), ['p1', 'p2', 'p3'], quorum: 2, reminder: $reminder, version: $version);
    }

    private function zone(): DateTimeZone
    {
        return new DateTimeZone('America/Toronto');
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, $this->zone());
    }

    /**
     * @param iterable<Session> $sessions
     *
     * @return list<string>
     */
    private function ids(iterable $sessions): array
    {
        $ids = [];

        foreach ($sessions as $session) {
            $ids[] = $session->id;
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function days(DateProposal $proposal): array
    {
        return array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $proposal->candidates);
    }
}
