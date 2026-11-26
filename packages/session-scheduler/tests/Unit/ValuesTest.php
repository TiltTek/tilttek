<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionScheduler\DateProposal;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Internal\Identifier;
use Tilttek\SessionScheduler\Internal\Moment;
use Tilttek\SessionScheduler\Reply;
use Tilttek\SessionScheduler\Series;
use Tilttek\SessionScheduler\Session;
use Tilttek\SessionScheduler\SessionQuery;
use Tilttek\SessionScheduler\SessionStatus;

#[CoversClass(Series::class)]
#[CoversClass(Session::class)]
#[CoversClass(SessionQuery::class)]
#[CoversClass(DateProposal::class)]
#[CoversClass(Reply::class)]
#[CoversClass(SessionStatus::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Moment::class)]
#[CoversClass(InvalidSchedule::class)]
final class ValuesTest extends TestCase
{
    public function testASeriesKeepsItsValues(): void
    {
        $series = self::aSeries(players: ['p1', 'p2'], minutes: 240, quorum: 2, notice: 48, reminder: 6, version: 3);

        self::assertSame('s1', $series->id);
        self::assertSame('c1', $series->campaign);
        self::assertSame('gm', $series->gameMaster);
        self::assertSame('2027-03-05T19:00:00-05:00', $series->start->format('c'));
        self::assertSame(['p1', 'p2'], $series->players);
        self::assertSame(240, $series->minutes);
        self::assertSame(2, $series->quorum);
        self::assertSame(48, $series->notice);
        self::assertSame(6, $series->reminder);
        self::assertSame(3, $series->version);

        $next = $series->withVersion(4);
        self::assertSame(4, $next->version);
        self::assertSame([$series->id, $series->campaign, $series->gameMaster, $series->players, $series->minutes, $series->quorum, $series->notice, $series->reminder], [$next->id, $next->campaign, $next->gameMaster, $next->players, $next->minutes, $next->quorum, $next->notice, $next->reminder]);
        self::assertSame($series->rule, $next->rule);
        self::assertTrue(Moment::same($series->start, $next->start));
    }

    public function testASeriesHasDefaults(): void
    {
        $series = new Series('s1', 'c1', 'gm', new DateTimeImmutable('2027-03-05 19:00'), RecurrenceRule::parse('FREQ=WEEKLY'), []);

        self::assertSame(180, $series->minutes);
        self::assertSame(1, $series->quorum);
        self::assertSame(24, $series->notice);
        self::assertNull($series->reminder);
        self::assertSame(0, $series->version);
    }

    public function testASeriesAcceptsItsBounds(): void
    {
        $players = array_map(static fn (int $n): string => 'p'.$n, range(1, 50));

        self::assertCount(50, self::aSeries(id: 'a'.str_repeat('b', 95), players: $players, minutes: 15, quorum: 50, notice: 0, reminder: 1)->players);
        self::assertSame(1440, self::aSeries(minutes: 1440, notice: 720, reminder: 168)->minutes);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidSeries(): iterable
    {
        yield 'identifier' => [static fn (): Series => self::aSeries(id: '1s'), 'The identifier of a series is 1 to 128'];
        yield 'long identifier' => [static fn (): Series => self::aSeries(id: 'a'.str_repeat('b', 96)), 'The identifier of a series is at most 96 characters.'];
        yield 'campaign' => [static fn (): Series => self::aSeries(campaign: 'c 1'), 'The identifier of a campaign'];
        yield 'game master' => [static fn (): Series => self::aSeries(gameMaster: 'gm@x'), 'The identifier of a member'];
        yield 'player' => [static fn (): Series => self::aSeries(players: ['p 1']), 'The identifier of a member'];
        yield 'player twice' => [static fn (): Series => self::aSeries(players: ['p1', 'p1']), 'The member p1 comes twice in a series.'];
        yield 'too many players' => [static fn (): Series => self::aSeries(players: array_map(static fn (int $n): string => 'p'.$n, range(1, 51))), 'A series holds at most 50 members.'];
        yield 'game master playing' => [static fn (): Series => self::aSeries(players: ['gm']), 'The game master of a series is not one of its players.'];
        yield 'short' => [static fn (): Series => self::aSeries(minutes: 14), 'A session lasts 15 to 1440 minutes.'];
        yield 'long' => [static fn (): Series => self::aSeries(minutes: 1441), 'A session lasts 15 to 1440 minutes.'];
        yield 'no quorum' => [static fn (): Series => self::aSeries(quorum: 0), 'The quorum of a series is 1 to its number of players.'];
        yield 'quorum above players' => [static fn (): Series => self::aSeries(players: ['p1'], quorum: 2), 'The quorum of a series is 1 to its number of players.'];
        yield 'quorum without players' => [static fn (): Series => self::aSeries(quorum: 2), 'The quorum of a series is 1 to its number of players.'];
        yield 'negative notice' => [static fn (): Series => self::aSeries(notice: -1), 'The notice of an absence is 0 to 720 hours.'];
        yield 'long notice' => [static fn (): Series => self::aSeries(notice: 721), 'The notice of an absence is 0 to 720 hours.'];
        yield 'no reminder hours' => [static fn (): Series => self::aSeries(reminder: 0), 'A reminder is due 1 to 168 hours before a session.'];
        yield 'early reminder' => [static fn (): Series => self::aSeries(reminder: 169), 'A reminder is due 1 to 168 hours before a session.'];
        yield 'version' => [static fn (): Series => self::aSeries(version: -1), 'The version of a series is 0 or more.'];
    }

    #[DataProvider('invalidSeries')]
    public function testASeriesOutOfItsBoundsIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testASeriesTellsItsOccurrences(): void
    {
        $series = self::aSeries();

        self::assertTrue($series->isOccurrence(new DateTimeImmutable('2027-03-12 19:00', new DateTimeZone('America/Toronto'))));
        self::assertTrue($series->isOccurrence(new DateTimeImmutable('2027-03-13 00:00', new DateTimeZone('UTC'))));
        self::assertFalse($series->isOccurrence(new DateTimeImmutable('2027-03-12 19:01', new DateTimeZone('America/Toronto'))));
        self::assertFalse($series->isOccurrence(new DateTimeImmutable('2027-03-11 19:00', new DateTimeZone('America/Toronto'))));
        self::assertCount(3, $series->recurrence()->between(null, null, 3));
    }

    public function testASessionKeepsItsValues(): void
    {
        $start = new DateTimeImmutable('2027-03-05 19:00', new DateTimeZone('America/Toronto'));
        $session = new Session('s1:1', 's1', $start, 90, $start, true, SessionStatus::Held, 'sub', ['p1' => Reply::Coming], 2);

        self::assertSame(['s1:1', 's1', 90, true, SessionStatus::Held, 'sub', ['p1' => Reply::Coming], 2], [$session->id, $session->series, $session->minutes, $session->offCalendar, $session->status, $session->gameMaster, $session->replies, $session->version]);
        self::assertSame('2027-03-05 20:30', $session->endsAt()->format('Y-m-d H:i'));
        self::assertSame(Reply::Coming, $session->reply('p1'));
        self::assertNull($session->reply('p2'));
        self::assertFalse($session->isRescheduled());

        $moved = $session->movedTo($start->modify('+1 day'), false);
        self::assertSame([], $moved->replies);
        self::assertFalse($moved->offCalendar);
        self::assertTrue($moved->isRescheduled());
        self::assertSame([$session->id, $session->series, $session->minutes, $session->status, $session->gameMaster, $session->version], [$moved->id, $moved->series, $moved->minutes, $moved->status, $moved->gameMaster, $moved->version]);
        self::assertTrue(Moment::same($start, $moved->occurrence ?? $start->modify('+1 year')));

        $this->assertKept($session, $session->withVersion(3), version: 3);
        $this->assertKept($session, $session->withStatus(SessionStatus::Cancelled), status: SessionStatus::Cancelled);
        $this->assertKept($session, $session->withGameMaster(null), gameMaster: null);
        $this->assertKept($session, $session->withReply('p2', Reply::Absent), replies: ['p1' => Reply::Coming, 'p2' => Reply::Absent]);
        self::assertSame(['p1' => Reply::AbsentLate], $session->withReply('p1', Reply::AbsentLate)->replies);
    }

    public function testASessionHasDefaults(): void
    {
        $session = new Session('s1:1', 's1', new DateTimeImmutable('2027-03-05 19:00'), 90);

        self::assertNull($session->occurrence);
        self::assertFalse($session->offCalendar);
        self::assertSame(SessionStatus::Planned, $session->status);
        self::assertNull($session->gameMaster);
        self::assertSame([], $session->replies);
        self::assertSame(0, $session->version);
        self::assertFalse($session->isRescheduled());
    }

    public function testASessionAcceptsItsBounds(): void
    {
        $replies = array_fill_keys(array_map(static fn (int $n): string => 'p'.$n, range(1, 51)), Reply::Coming);

        self::assertCount(51, new Session('s1:1', 's1', new DateTimeImmutable(), 15, replies: $replies)->replies);
        self::assertSame(1440, new Session('s1:1', 's1', new DateTimeImmutable(), 1440)->minutes);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidSessions(): iterable
    {
        yield 'identifier' => [static fn (): Session => self::aSession(id: ''), 'The identifier of a session'];
        yield 'series' => [static fn (): Session => self::aSession(series: '-'), 'The identifier of a series'];
        yield 'game master' => [static fn (): Session => self::aSession(gameMaster: 'g m'), 'The identifier of a member'];
        yield 'reply' => [static fn (): Session => self::aSession(replies: ['p 1' => Reply::Coming]), 'The identifier of a member'];
        yield 'short' => [static fn (): Session => self::aSession(minutes: 14), 'A session lasts 15 to 1440 minutes.'];
        yield 'long' => [static fn (): Session => self::aSession(minutes: 1441), 'A session lasts 15 to 1440 minutes.'];
        yield 'replies' => [static fn (): Session => self::aSession(replies: array_fill_keys(array_map(static fn (int $n): string => 'p'.$n, range(1, 52)), Reply::Coming)), 'A session holds at most 51 replies.'];
        yield 'version' => [static fn (): Session => self::aSession(version: -1), 'The version of a session is 0 or more.'];
    }

    #[DataProvider('invalidSessions')]
    public function testASessionOutOfItsBoundsIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testAProposalSortsItsDates(): void
    {
        $late = new DateTimeImmutable('2027-03-09 19:00');
        $early = new DateTimeImmutable('2027-03-08 19:00');
        $proposal = new DateProposal('s1', 's1:1', [$late, $early], ['gm', 'p1'], 15);

        self::assertSame(['s1', 's1:1', ['gm', 'p1'], 15], [$proposal->series, $proposal->session, $proposal->members, $proposal->minutes]);
        self::assertSame(['2027-03-08', '2027-03-09'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $proposal->candidates));
        self::assertNull(new DateProposal('s1', null, [$late], [], 1440)->session);
        self::assertCount(20, new DateProposal('s1', null, array_map(static fn (int $n): DateTimeImmutable => $early->modify('+'.$n.' days'), range(1, 20)), [], 60)->candidates);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidProposals(): iterable
    {
        $date = new DateTimeImmutable('2027-03-08 19:00');

        yield 'series' => [static fn (): DateProposal => self::aProposal(series: '1'), 'The identifier of a series'];
        yield 'session' => [static fn (): DateProposal => self::aProposal(session: '1'), 'The identifier of a session'];
        yield 'no date' => [static fn (): DateProposal => self::aProposal(candidates: []), 'A proposal holds 1 to 20 dates.'];
        yield 'too many dates' => [static fn (): DateProposal => self::aProposal(candidates: array_map(static fn (int $n): DateTimeImmutable => $date->modify('+'.$n.' days'), range(1, 21))), 'A proposal holds 1 to 20 dates.'];
        yield 'same date' => [static fn (): DateProposal => self::aProposal(candidates: [$date, $date->setTimezone(new DateTimeZone('Asia/Tokyo'))]), 'A proposal holds each date once.'];
        yield 'member' => [static fn (): DateProposal => self::aProposal(members: ['p 1']), 'The identifier of a member'];
        yield 'member twice' => [static fn (): DateProposal => self::aProposal(members: ['p1', 'p1']), 'The member p1 comes twice in a proposal.'];
        yield 'too many members' => [static fn (): DateProposal => self::aProposal(members: array_map(static fn (int $n): string => 'p'.$n, range(1, 52))), 'A proposal holds at most 51 members.'];
        yield 'short' => [static fn (): DateProposal => self::aProposal(minutes: 14), 'A session lasts 15 to 1440 minutes.'];
        yield 'long' => [static fn (): DateProposal => self::aProposal(minutes: 1441), 'A session lasts 15 to 1440 minutes.'];
    }

    #[DataProvider('invalidProposals')]
    public function testAProposalOutOfItsBoundsIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testAQueryMatchesItsWindowBothEndsIncluded(): void
    {
        $from = new DateTimeImmutable('2027-03-05 19:00');
        $through = new DateTimeImmutable('2027-03-12 19:00');
        $query = SessionQuery::ofSeries('s1', $from, $through, SessionStatus::Planned);

        self::assertSame(['s1', SessionStatus::Planned], [$query->series, $query->status]);
        self::assertTrue($query->matches($this->session('s1', $from)));
        self::assertTrue($query->matches($this->session('s1', $through)));
        self::assertFalse($query->matches($this->session('s1', $from->modify('-1 second'))));
        self::assertFalse($query->matches($this->session('s1', $through->modify('+1 second'))));
        self::assertFalse($query->matches($this->session('s2', $from)));
        self::assertFalse($query->matches($this->session('s1', $from)->withStatus(SessionStatus::Held)));

        $open = new SessionQuery();
        self::assertNull($open->series);
        self::assertNull($open->from);
        self::assertNull($open->through);
        self::assertNull($open->status);
        self::assertTrue($open->matches($this->session('s2', $from)->withStatus(SessionStatus::Cancelled)));

        $starting = SessionQuery::starting($from, $from);
        self::assertNull($starting->series);
        self::assertSame('2027-03-05 19:00', $starting->through?->format('Y-m-d H:i'));
        self::assertTrue($starting->matches($this->session('s9', $from)));
        self::assertSame(SessionStatus::Held, SessionQuery::starting($from, $through, SessionStatus::Held)->status);
    }

    public function testAQueryRefusesAWindowEndingBeforeItStarts(): void
    {
        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('A window of sessions ends after it starts.');

        new SessionQuery(null, new DateTimeImmutable('2027-03-05 19:00:01'), new DateTimeImmutable('2027-03-05 19:00'));
    }

    public function testAQueryWithOnlyOneEndOfItsWindowIsNotRefused(): void
    {
        $moment = new DateTimeImmutable('2027-03-05 19:00');

        self::assertSame('2027-03-05 19:00', new SessionQuery(null, $moment)->from?->format('Y-m-d H:i'));
        self::assertSame('2027-03-05 19:00', new SessionQuery(null, null, $moment)->through?->format('Y-m-d H:i'));
    }

    public function testAQueryRefusesABadSeries(): void
    {
        $this->expectException(InvalidSchedule::class);

        SessionQuery::ofSeries('9');
    }

    public function testRepliesTellAbsences(): void
    {
        self::assertFalse(Reply::Coming->isAbsent());
        self::assertTrue(Reply::Absent->isAbsent());
        self::assertTrue(Reply::AbsentLate->isAbsent());
        self::assertSame('absent-late', Reply::AbsentLate->value);
        self::assertSame('planned', SessionStatus::Planned->value);
    }

    public function testMomentsAreComparedByInstant(): void
    {
        $moment = new DateTimeImmutable('2027-03-05 19:00:00.5', new DateTimeZone('America/Toronto'));

        self::assertTrue(Moment::same($moment, $moment->setTimezone(new DateTimeZone('UTC'))));
        self::assertFalse(Moment::same($moment, $moment->modify('+1 microsecond')));
        self::assertSame('20270306T000000Z', Moment::stamp($moment));
    }

    public function testIdentifiersAreChecked(): void
    {
        self::assertSame('a'.str_repeat('1', 127), Identifier::check('a'.str_repeat('1', 127), 'a member'));
        self::assertSame('A_b.c:d-e', Identifier::check('A_b.c:d-e', 'a member'));
        self::assertSame(['p2', 'p1'], Identifier::members(['p2', 'p1'], 2, 'A test'));

        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a member is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.');

        Identifier::check('a'.str_repeat('1', 128), 'a member');
    }

    public function testIdentifierListsAreBounded(): void
    {
        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('A test holds at most 1 members.');

        Identifier::members(['p1', 'p2'], 1, 'A test');
    }

    /**
     * @param list<string> $players
     */
    private static function aSeries(string $id = 's1', string $campaign = 'c1', string $gameMaster = 'gm', array $players = [], int $minutes = 180, int $quorum = 1, int $notice = 24, ?int $reminder = null, int $version = 0): Series
    {
        return new Series($id, $campaign, $gameMaster, new DateTimeImmutable('2027-03-05 19:00', new DateTimeZone('America/Toronto')), RecurrenceRule::parse('FREQ=WEEKLY'), $players, $minutes, $quorum, $notice, $reminder, $version);
    }

    /**
     * @param array<string, Reply> $replies
     */
    private static function aSession(string $id = 's1:1', string $series = 's1', int $minutes = 60, ?string $gameMaster = null, array $replies = [], int $version = 0): Session
    {
        return new Session($id, $series, new DateTimeImmutable(), $minutes, gameMaster: $gameMaster, replies: $replies, version: $version);
    }

    /**
     * @param list<DateTimeImmutable>|null $candidates
     * @param list<string>                 $members
     */
    private static function aProposal(string $series = 's1', ?string $session = null, ?array $candidates = null, array $members = [], int $minutes = 60): DateProposal
    {
        return new DateProposal($series, $session, $candidates ?? [new DateTimeImmutable('2027-03-08 19:00')], $members, $minutes);
    }

    private function session(string $series, DateTimeImmutable $startsAt): Session
    {
        return new Session($series.':1', $series, $startsAt, 60);
    }

    /**
     * @param array<string, Reply>|false $replies
     */
    private function assertKept(Session $before, Session $after, int|false $version = false, SessionStatus|false $status = false, string|false|null $gameMaster = false, array|false $replies = false): void
    {
        self::assertSame(false === $version ? $before->version : $version, $after->version);
        self::assertSame(false === $status ? $before->status : $status, $after->status);
        self::assertSame(false === $gameMaster ? $before->gameMaster : $gameMaster, $after->gameMaster);
        self::assertSame(false === $replies ? $before->replies : $replies, $after->replies);
        self::assertSame([$before->id, $before->series, $before->minutes, $before->offCalendar], [$after->id, $after->series, $after->minutes, $after->offCalendar]);
        self::assertTrue(Moment::same($before->startsAt, $after->startsAt));
        self::assertNotNull($after->occurrence);
        self::assertNotNull($before->occurrence);
        self::assertTrue(Moment::same($before->occurrence, $after->occurrence));
    }
}
