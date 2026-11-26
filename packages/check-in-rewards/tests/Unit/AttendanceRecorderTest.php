<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Exception\UnknownVerb;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Verbs;
use Tilttek\CheckInRewards\AttendanceRecorder;
use Tilttek\CheckInRewards\AttendanceVerbs;
use Tilttek\CheckInRewards\Tests\Unit\Fixture\Door;

#[CoversClass(AttendanceRecorder::class)]
final class AttendanceRecorderTest extends TestCase
{
    public function testAnAdmissionIsRecordedWithItsFirstTimes(): void
    {
        $door = new Door();
        $door->scan('44', 'con.panel-7.hall-b.art');

        $entries = $door->journal->read(0, 100);
        self::assertSame(
            [AttendanceVerbs::RECORDED, AttendanceVerbs::SESSION, AttendanceVerbs::DAY, AttendanceVerbs::PLACE, AttendanceVerbs::WEEK],
            array_map(static fn (Entry $entry): string => $entry->activity->verb->value, $entries),
        );

        $recorded = $entries[0]->activity;
        self::assertSame([
            'key' => 'check-in:scan-1',
            'member' => 'member:44',
            'verb' => 'attendance.recorded',
            'occurred_at' => '2027-03-05T18:00:00+00:00',
            'object' => 'session:panel-7',
            'quantity' => 1,
            'context' => ['day' => '2027-03-05', 'event' => 'event:con', 'place' => 'hall-b', 'track' => 'art'],
        ], $recorded->toArray());

        $session = $entries[1]->activity->toArray();
        self::assertSame('event:con', $session['object']);
        self::assertSame(['event' => 'event:con'], $session['context']);
        self::assertSame('member:44', $session['member']);
        self::assertSame('2027-03-05T18:00:00+00:00', $session['occurred_at']);
        self::assertSame('attendance.session:'.$this->digest(['member:44', 'event:con', 'session:panel-7']), $session['key']);

        $day = $entries[2]->activity->toArray();
        self::assertSame('event:con', $day['object']);
        self::assertSame(['day' => '2027-03-05', 'event' => 'event:con'], $day['context']);
        self::assertSame('attendance.day:'.$this->digest(['member:44', 'event:con', '2027-03-05']), $day['key']);

        $place = $entries[3]->activity->toArray();
        self::assertSame('event:con', $place['object']);
        self::assertSame(['event' => 'event:con', 'place' => 'hall-b'], $place['context']);
        self::assertSame('attendance.place:'.$this->digest(['member:44', 'event:con', 'hall-b']), $place['key']);

        $week = $entries[4]->activity->toArray();
        self::assertNull($week['object']);
        self::assertSame(['week' => '2027-W09'], $week['context']);
        self::assertSame('attendance.week:'.$this->digest(['member:44', '2027-W09']), $week['key']);
    }

    public function testAnAdmissionWithoutEventNorPlaceRecordsNeither(): void
    {
        $door = new Door();
        $door->scan('44', 'shop');

        $entries = $door->journal->read(0, 100);
        self::assertSame(
            [AttendanceVerbs::RECORDED, AttendanceVerbs::SESSION, AttendanceVerbs::DAY, AttendanceVerbs::WEEK],
            array_map(static fn (Entry $entry): string => $entry->activity->verb->value, $entries),
        );
        self::assertSame(['day' => '2027-03-05'], $entries[0]->activity->context->values);
        self::assertSame([], $entries[1]->activity->context->values);
        self::assertNull($entries[1]->activity->object);
        self::assertSame('attendance.session:'.$this->digest(['member:44', null, 'session:shop']), $entries[1]->activity->key->value);
    }

    public function testAScanHandedAgainCountsOnce(): void
    {
        $door = new Door();
        $checkIn = $door->scan('44', 'con.panel-7.hall-b');

        self::assertTrue($door->recorder->record($checkIn->id));
        self::assertTrue($door->recorder->record($checkIn->id));

        self::assertSame(5, $door->log->lastPosition());
        self::assertSame(1, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::RECORDED))->count);
    }

    public function testAReEntryToTheSameSessionAddsAnAdmissionButNoFirstTime(): void
    {
        $door = new Door();
        $door->scan('44', 'con.panel-7.hall-b');
        $door->scan('44', 'con.panel-7.hall-b');

        self::assertSame(2, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::RECORDED))->count);
        self::assertSame(1, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::SESSION))->count);
        self::assertSame(1, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::DAY))->count);
        self::assertSame(1, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::PLACE))->count);
        self::assertSame(1, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::WEEK))->count);
    }

    public function testTheSameSessionOfTwoEventsIsTwoSessions(): void
    {
        $door = new Door();
        $door->scan('44', 'con.panel-7.hall-b');
        $door->scan('44', 'fest.panel-7.hall-b');

        self::assertSame(2, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::SESSION))->count);
        self::assertSame(2, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::PLACE))->count);
        self::assertSame(2, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::DAY))->count);
        self::assertSame(1, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::WEEK))->count);
    }

    public function testTheDayAndTheWeekAreTheEventsTimezone(): void
    {
        // Sunday 2027-03-07 at 23:30 in Montréal is Monday 04:30 in UTC, in the next ISO week.
        $door = new Door(new DateTimeZone('America/Montreal'), '2027-03-08 05:00:00 UTC');
        $door->scan('44', 'con.panel-7', '2027-03-08 04:30:00 UTC');

        $entries = $door->journal->read(0, 100);
        self::assertSame('2027-03-07', $entries[0]->activity->context->get('day'));
        self::assertSame('2027-03-07', $entries[2]->activity->context->get('day'));
        self::assertSame('2027-W09', $entries[3]->activity->context->get('week'));
        self::assertSame('2027-03-08T04:30:00+00:00', $entries[0]->activity->toArray()['occurred_at']);
    }

    public function testTheWeekIsTheIsoWeekOfItsYear(): void
    {
        // 2027-01-01 is a Friday of the last ISO week of 2026.
        $door = new Door(now: '2027-01-01 12:00:00 UTC');
        $door->scan('44', 'shop');

        self::assertSame('2026-W53', $door->journal->read(0, 100)[3]->activity->context->get('week'));
    }

    public function testAScanIsNeverDatedAfterTheServerReceivedIt(): void
    {
        $door = new Door();
        $door->scan('44', 'shop', '2027-03-06 03:00:00 UTC', recordedAt: '2027-03-05 17:00:00 UTC');

        $activity = $door->journal->read(0, 1)[0]->activity;
        self::assertSame('2027-03-05T17:00:00+00:00', $activity->toArray()['occurred_at']);
        self::assertSame('2027-03-05', $activity->context->get('day'));
    }

    public function testAnOfflineScanKeepsTheMomentItWasMade(): void
    {
        $door = new Door();
        $door->scan('44', 'shop', '2027-03-04 10:00:00 UTC');

        self::assertSame('2027-03-04T10:00:00+00:00', $door->journal->read(0, 1)[0]->activity->toArray()['occurred_at']);
    }

    public function testAnErasedOrUnknownMemberIsLeftOut(): void
    {
        $door = new Door();
        $door->erase('44');

        $checkIn = $door->scan('44', 'con.panel-7');

        self::assertFalse($door->recorder->record($checkIn->id));
        self::assertFalse($door->recorder->record(ScanId::of('ticket-of-no-member')));
        self::assertSame(0, $door->log->lastPosition());
    }

    public function testAScanTheLedgerDoesNotHoldIsLeftOut(): void
    {
        $door = new Door();

        self::assertFalse($door->recorder->record(ScanId::of('forged')));
        self::assertSame(0, $door->log->lastPosition());
    }

    /**
     * @return iterable<string, array{Direction, ScanOutcome, ?int, ?ScanOutcome}>
     */
    public static function notAnAdmission(): iterable
    {
        yield 'a check-out' => [Direction::Out, ScanOutcome::CheckedOut, 1, null];
        yield 'a refusal' => [Direction::In, ScanOutcome::Revoked, null, null];
        yield 'a conflict' => [Direction::In, ScanOutcome::Admitted, 1, ScanOutcome::Revoked];
    }

    #[DataProvider('notAnAdmission')]
    public function testWhatIsNotAnAdmissionIsLeftOut(Direction $direction, ScanOutcome $outcome, ?int $sequence, ?ScanOutcome $conflict): void
    {
        $door = new Door();
        $door->ledger->append(CheckIn::restore(ScanId::of('scan-9'), 'ticket:44', CheckpointId::of('con.panel-7'), $direction, $outcome, DeviceId::of('desk-1'), $door->clock->now(), $door->clock->now(), $sequence, true, $conflict));

        self::assertFalse($door->recorder->record(ScanId::of('scan-9')));
        self::assertSame(0, $door->log->lastPosition());
    }

    public function testAnOfflineAdmissionWithoutConflictIsRecorded(): void
    {
        $door = new Door();
        $door->ledger->append(CheckIn::restore(ScanId::of('scan-9'), 'ticket:44', CheckpointId::of('con.panel-7'), Direction::In, ScanOutcome::Admitted, DeviceId::of('desk-1'), $door->clock->now(), $door->clock->now(), 1, true));

        self::assertTrue($door->recorder->record(ScanId::of('scan-9')));
        self::assertSame(1, $door->journal->totals(Filter::all()->verbs(AttendanceVerbs::RECORDED))->count);
    }

    public function testAJournalThatDeclaresItsVerbsNeedsThePatternOfTheLibrary(): void
    {
        $door = new Door();
        $journal = new Journal(new InMemoryActivityLog(), $door->clock, Verbs::of([AttendanceVerbs::PATTERN]));
        $recorder = new AttendanceRecorder($door->ledger, $door, $journal);
        $checkIn = $door->scan('44', 'con.panel-7.hall-b');

        $recorder->record($checkIn->id);

        self::assertSame(5, $journal->totals(Filter::all()->member(ResourceReference::of('member', '44')))->count);

        $this->expectException(UnknownVerb::class);
        new AttendanceRecorder($door->ledger, $door, new Journal(new InMemoryActivityLog(), $door->clock, Verbs::of(['game.*'])))->record($checkIn->id);
    }

    public function testTheVerbsOfTheLibraryAreItsPattern(): void
    {
        $verbs = Verbs::of([AttendanceVerbs::PATTERN]);

        foreach ([AttendanceVerbs::RECORDED, AttendanceVerbs::SESSION, AttendanceVerbs::DAY, AttendanceVerbs::PLACE, AttendanceVerbs::WEEK] as $verb) {
            self::assertTrue($verbs->allows(Activity::of('k', 'member:1', $verb, new DateTimeImmutable())->verb));
        }
    }

    /**
     * @param list<?string> $parts
     */
    private function digest(array $parts): string
    {
        $text = '';

        foreach ($parts as $part) {
            $text .= \strlen($part ?? '').':'.$part.';';
        }

        return hash('sha256', $text);
    }
}
