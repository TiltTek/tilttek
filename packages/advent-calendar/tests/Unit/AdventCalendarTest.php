<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Tests\Unit;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\BoxState;
use Tilttek\AdventCalendar\BoxView;
use Tilttek\AdventCalendar\Calendar;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\Exception\BoxNotOpenable;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendar\OpenedBox;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;
use Tilttek\AdventCalendar\Surprise;
use Tilttek\AdventCalendar\Tests\Unit\Fixture\RacingOpeningStore;
use Tilttek\AdventCalendar\Tests\Unit\Fixture\Recorder;

#[CoversClass(AdventCalendar::class)]
#[CoversClass(OpenedBox::class)]
#[CoversClass(BoxView::class)]
final class AdventCalendarTest extends TestCase
{
    private MockClock $clock;

    private Recorder $events;

    private InMemoryOpeningStore $store;

    private AdventCalendar $advent;

    private ResourceReference $member;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-12-03 10:00:00', 'Europe/Paris');
        $this->events = new Recorder();
        $this->store = new InMemoryOpeningStore();
        $this->advent = new AdventCalendar($this->store, $this->clock, $this->events);
        $this->member = ResourceReference::of('member', '1');
    }

    public function testABoxOpensAtTheFirstMomentOfItsDayAndIsToldOnce(): void
    {
        $calendar = $this->calendar();
        $this->clock->modify('2026-12-03 00:00:00');

        $opened = $this->advent->open($calendar, $this->member, 3);

        self::assertTrue($opened->first);
        self::assertSame('badge-3', $opened->surprise->reference);
        self::assertSame(['advent-2026', 3, '2026-12-03T00:00:00+01:00'], [$opened->opening->calendar, $opened->opening->day, $opened->opening->openedAt->format(DateTimeInterface::ATOM)]);
        self::assertTrue($opened->opening->member->equals($this->member));
        self::assertCount(1, $this->events->events);
        $event = $this->events->events[0];
        self::assertInstanceOf(BoxOpened::class, $event);
        self::assertSame(['advent-2026', 3, 'badge-3', '2026-12-03T00:00:00+01:00'], [$event->calendar, $event->day, $event->surprise->reference, $event->openedAt->format(DateTimeInterface::ATOM)]);
        self::assertTrue($event->member->equals($this->member));
        self::assertNotNull($this->store->find('advent-2026', $this->member, 3));
    }

    public function testOpeningTwiceAnswersTheFirstOpeningAndTellsNothing(): void
    {
        $calendar = $this->calendar();
        $first = $this->advent->open($calendar, $this->member, 3);
        $this->clock->modify('2026-12-03 18:00:00');

        $again = $this->advent->open($calendar, $this->member, 3);

        self::assertFalse($again->first);
        self::assertSame('2026-12-03T10:00:00+01:00', $again->opening->openedAt->format(DateTimeInterface::ATOM));
        self::assertSame($first->opening->openedAt, $again->opening->openedAt);
        self::assertSame('badge-3', $again->surprise->reference);
        self::assertCount(1, $this->events->events);
    }

    public function testAnOpenedBoxStaysOpenedAfterTheCalendarCloses(): void
    {
        $calendar = $this->calendar(OpeningPolicy::sameDay());
        $this->advent->open($calendar, $this->member, 3);
        $this->clock->modify('2027-02-01 10:00:00');

        self::assertFalse($this->advent->open($calendar, $this->member, 3)->first);
        self::assertSame(BoxState::Opened, $this->advent->box($calendar, $this->member, 3)->state);
    }

    public function testABoxDoesNotOpenBeforeItsDay(): void
    {
        $this->clock->modify('2026-12-03 23:59:59');

        try {
            $this->advent->open($this->calendar(), $this->member, 4);
            self::fail('The box should not open.');
        } catch (BoxNotOpenable $boxNotOpenable) {
            self::assertSame('The box 4 of the calendar "advent-2026" opens at 2026-12-04T00:00:00+01:00.', $boxNotOpenable->getMessage());
        }

        self::assertSame([], $this->events->events);
        self::assertNull($this->store->find('advent-2026', $this->member, 4));
    }

    public function testByDefaultAPastBoxOpensUntilTheCalendarCloses(): void
    {
        $calendar = $this->calendar();
        $this->clock->modify('2026-12-24 23:59:59');

        self::assertTrue($this->advent->open($calendar, $this->member, 1)->first);

        $this->clock->modify('2026-12-25 00:00:00');

        try {
            $this->advent->open($calendar, $this->member, 2);
            self::fail('The calendar is closed.');
        } catch (BoxNotOpenable $boxNotOpenable) {
            self::assertSame('The box 2 of the calendar "advent-2026" could be opened until 2026-12-25T00:00:00+01:00.', $boxNotOpenable->getMessage());
        }
    }

    public function testOnTheSameDayPolicyABoxIsMissedAtMidnight(): void
    {
        $calendar = $this->calendar(OpeningPolicy::sameDay());
        $this->clock->modify('2026-12-03 23:59:59');
        self::assertTrue($this->advent->open($calendar, $this->member, 3)->first);

        $this->clock->modify('2026-12-04 00:00:00');
        $this->expectException(BoxNotOpenable::class);
        $this->expectExceptionMessageIsOrContains('The box 3 of the calendar "advent-2026" could be opened until 2026-12-04T00:00:00+01:00.');

        $this->advent->open($calendar, ResourceReference::of('member', '2'), 3);
    }

    public function testACatchUpAcrossTheChangeOfTime(): void
    {
        $calendar = Calendar::of('fall', 'America/Toronto', '2026-10-31', [Surprise::badge('a'), Surprise::badge('b'), Surprise::badge('c')], OpeningPolicy::catchUp(1), '2026-11-30');
        $this->clock = new MockClock('2026-11-02 04:59:59', 'UTC');
        $this->advent = new AdventCalendar($this->store, $this->clock, $this->events);
        self::assertTrue($this->advent->open($calendar, $this->member, 1)->first);

        $this->clock->modify('2026-11-02 05:00:00');
        self::assertSame(BoxState::Missed, $this->advent->box($calendar, ResourceReference::of('member', '2'), 1)->state);
        self::assertSame(BoxState::Openable, $this->advent->box($calendar, $this->member, 2)->state);
    }

    public function testTheBoxesAsAMemberSeesThem(): void
    {
        $calendar = $this->calendar(OpeningPolicy::catchUp(1));
        $this->clock->modify('2026-12-01 12:00:00');
        $this->advent->open($calendar, $this->member, 1);
        $this->clock->modify('2026-12-04 08:00:00');
        $this->advent->open($calendar, $this->member, 4);
        $this->advent->open($calendar, ResourceReference::of('member', '2'), 3);

        $boxes = $this->advent->boxes($calendar, $this->member);

        self::assertCount(24, $boxes);
        self::assertSame(range(1, 24), array_map(static fn (BoxView $box): int => $box->day, $boxes));
        self::assertSame(
            [BoxState::Opened, BoxState::Missed, BoxState::Openable, BoxState::Opened, BoxState::Locked],
            array_map(static fn (BoxView $box): BoxState => $box->state, \array_slice($boxes, 0, 5)),
        );
        self::assertSame(['2026-12-01', 'badge-1', '2026-12-01T12:00:00+01:00'], [$boxes[0]->date, $boxes[0]->surprise?->reference, $boxes[0]->openedAt?->format(DateTimeInterface::ATOM)]);
        self::assertSame('badge-4', $boxes[3]->surprise?->reference);
        self::assertSame('2026-12-24', $boxes[23]->date);

        foreach ([1, 2, 4] as $index) {
            self::assertNull($boxes[$index]->surprise);
            self::assertNull($boxes[$index]->openedAt);
        }

        self::assertSame('2026-12-06T00:00:00+01:00', $boxes[3]->window->until->format(DateTimeInterface::ATOM));
    }

    public function testOneBox(): void
    {
        $calendar = $this->calendar();

        $box = $this->advent->box($calendar, $this->member, 3);

        self::assertSame([3, '2026-12-03', BoxState::Openable, null], [$box->day, $box->date, $box->state, $box->surprise]);
        self::assertSame(BoxState::Locked, $this->advent->box($calendar, $this->member, 24)->state);
    }

    public function testABoxOutsideTheCalendarIsRefusedBeforeTheStoreIsRead(): void
    {
        $advent = new AdventCalendar(new RacingOpeningStore(new Opening('advent-2026', $this->member, 25, new DateTimeImmutable())), $this->clock, $this->events);

        try {
            $advent->open($this->calendar(), $this->member, 25);
            self::fail('The box should be refused.');
        } catch (InvalidCalendar) {
        }

        $this->expectException(InvalidCalendar::class);
        $this->advent->box($this->calendar(), $this->member, 0);
    }

    public function testARequestThatLosesTheRaceAnswersTheOtherOpening(): void
    {
        $other = new Opening('advent-2026', $this->member, 3, new DateTimeImmutable('2026-12-03 09:59:59', new DateTimeZone('Europe/Paris')));
        $advent = new AdventCalendar(new RacingOpeningStore($other), $this->clock, $this->events);

        $opened = $advent->open($this->calendar(), $this->member, 3);

        self::assertFalse($opened->first);
        self::assertSame($other, $opened->opening);
        self::assertSame('badge-3', $opened->surprise->reference);
        self::assertSame([], $this->events->events);
    }

    public function testErasingAMember(): void
    {
        $calendar = $this->calendar();
        $this->advent->open($calendar, $this->member, 1);
        $this->advent->open($calendar, $this->member, 2);

        self::assertSame(2, $this->advent->erase($this->member));
        self::assertSame(BoxState::Openable, $this->advent->box($calendar, $this->member, 1)->state);
    }

    private function calendar(?OpeningPolicy $policy = null): Calendar
    {
        $surprises = [];

        for ($day = 1; $day <= 24; ++$day) {
            $surprises[] = Surprise::badge('badge-'.$day);
        }

        return Calendar::of('advent-2026', 'Europe/Paris', '2026-12-01', $surprises, $policy);
    }
}
