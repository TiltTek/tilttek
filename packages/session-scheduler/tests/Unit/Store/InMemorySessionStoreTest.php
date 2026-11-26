<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Exception\ScheduleConflict;
use Tilttek\SessionScheduler\Reply;
use Tilttek\SessionScheduler\Series;
use Tilttek\SessionScheduler\Session;
use Tilttek\SessionScheduler\SessionQuery;
use Tilttek\SessionScheduler\SessionStatus;
use Tilttek\SessionScheduler\Store\InMemorySessionStore;

#[CoversClass(InMemorySessionStore::class)]
#[CoversClass(ScheduleConflict::class)]
final class InMemorySessionStoreTest extends TestCase
{
    public function testASeriesIsKeptAtTheNextVersion(): void
    {
        self::assertNull(new InMemorySessionStore()->series('s1'));
        $store = new InMemorySessionStore();
        self::assertSame(1, $store->saveSeries($this->series())->version);
        self::assertSame(1, $store->series('s1')?->version);
        self::assertSame(2, $store->saveSeries($this->series(1))->version);
    }

    public function testANewSeriesIsKeptOnce(): void
    {
        $store = new InMemorySessionStore();
        $store->saveSeries($this->series());

        $this->expectException(ScheduleConflict::class);
        $this->expectExceptionMessageIsOrContains('The series s1 changed since it was read.');

        $store->saveSeries($this->series());
    }

    public function testAStaleSeriesIsRefused(): void
    {
        $this->expectException(ScheduleConflict::class);

        new InMemorySessionStore()->saveSeries($this->series(1));
    }

    public function testASessionIsKeptAtTheNextVersion(): void
    {
        self::assertNull(new InMemorySessionStore()->session('a'));
        $store = new InMemorySessionStore();
        self::assertSame(1, $store->save($this->session('a', '2027-03-05'))->version);
        self::assertSame(1, $store->session('a')?->version);
        self::assertSame(2, $store->save($this->session('a', '2027-03-05', version: 1))->version);
    }

    public function testANewSessionIsKeptOnce(): void
    {
        $store = new InMemorySessionStore();
        $store->save($this->session('a', '2027-03-05'));

        $this->expectException(ScheduleConflict::class);
        $this->expectExceptionMessageIsOrContains('The session a changed since it was read.');

        $store->save($this->session('a', '2027-03-05'));
    }

    public function testAStaleSessionIsRefused(): void
    {
        $this->expectException(ScheduleConflict::class);

        new InMemorySessionStore()->save($this->session('a', '2027-03-05', version: 2));
    }

    public function testSessionsAreReadEarliestFirstThenByIdentifier(): void
    {
        $store = new InMemorySessionStore();
        $store->save($this->session('c', '2027-03-12'));
        $store->save($this->session('b', '2027-03-05'));
        $store->save($this->session('a', '2027-03-05'));
        $store->save($this->session('d', '2027-03-19', 's2'));

        self::assertSame(['a', 'b', 'c'], $this->ids($store->sessions(SessionQuery::ofSeries('s1'), 10)));
        self::assertSame(['a', 'b'], $this->ids($store->sessions(SessionQuery::ofSeries('s1'), 2)));
        self::assertSame(['a'], $this->ids($store->sessions(SessionQuery::ofSeries('s1'), 1)));
        self::assertSame(['c', 'd'], $this->ids($store->sessions(SessionQuery::starting(new DateTimeImmutable('2027-03-06'), new DateTimeImmutable('2027-03-30')), 10)));
    }

    public function testAReadTakesAtLeastOneSession(): void
    {
        $this->expectException(InvalidSchedule::class);
        $this->expectExceptionMessageIsOrContains('A store reads at least one session at once.');

        new InMemorySessionStore()->sessions(new SessionQuery(), 0);
    }

    public function testComingCountsPlannedUpcomingSessionsOnly(): void
    {
        $store = new InMemorySessionStore();
        $store->save($this->session('a', '2027-03-05')->withReply('p1', Reply::Coming));
        $store->save($this->session('b', '2027-03-12')->withReply('p1', Reply::Coming));
        $store->save($this->session('c', '2027-03-19')->withReply('p1', Reply::Absent));
        $store->save($this->session('d', '2027-03-26')->withReply('p1', Reply::Coming)->withStatus(SessionStatus::Cancelled));
        $store->save($this->session('e', '2027-04-02')->withReply('p2', Reply::Coming));

        self::assertSame(2, $store->countComing('p1', new DateTimeImmutable('2027-03-01')));
        self::assertSame(1, $store->countComing('p1', new DateTimeImmutable('2027-03-05 19:00')));
        self::assertSame(0, $store->countComing('p3', new DateTimeImmutable('2027-03-01')));
    }

    private function series(int $version = 0): Series
    {
        return new Series('s1', 'c1', 'gm', new DateTimeImmutable('2027-03-05 19:00'), RecurrenceRule::parse('FREQ=WEEKLY'), [], version: $version);
    }

    private function session(string $id, string $day, string $series = 's1', int $version = 0): Session
    {
        return new Session($id, $series, new DateTimeImmutable($day.' 19:00'), 60, version: $version);
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
}
