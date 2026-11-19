<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\AdventCalendar\BoxState;
use Tilttek\AdventCalendar\BoxView;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\PolicyKind;
use Tilttek\AdventCalendar\Surprise;
use Tilttek\AdventCalendar\SurpriseKind;
use Tilttek\AdventCalendarBundle\Application\CalendarEntry;
use Tilttek\AdventCalendarBundle\Application\Calendars;
use Tilttek\AdventCalendarBundle\Application\Celebrations;
use Tilttek\AdventCalendarBundle\Application\ConfiguredCalendars;
use Tilttek\AdventCalendarBundle\Application\Members;
use Tilttek\AdventCalendarBundle\Application\NoMember;
use Tilttek\AdventCalendarBundle\Application\VisitorBoxes;
use Tilttek\AdventCalendarBundle\Infrastructure\EventListener\CelebrationListener;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\Calendars as Fixtures;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\FixedMember;

#[CoversClass(Members::class)]
#[CoversClass(NoMember::class)]
#[CoversClass(ConfiguredCalendars::class)]
#[CoversClass(Calendars::class)]
#[CoversClass(CalendarEntry::class)]
#[CoversClass(Celebrations::class)]
#[CoversClass(CelebrationListener::class)]
#[CoversClass(VisitorBoxes::class)]
final class ApplicationTest extends TestCase
{
    public function testTheMemberIsTheResolversAndAVisitorIsNobody(): void
    {
        self::assertSame('member:44', new Members(new FixedMember('member:44'))->current()?->toString());
        self::assertNull(new Members(new FixedMember())->current());
        self::assertNull(new Members(new NoMember())->current());
        self::assertNull(new NoMember()->current());
    }

    public function testAnEmailAddressIsNeverTheKeyOfAMember(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The member resolver gave "member:…", an identifier with "@": a member is referenced by a stable identifier, never by an e-mail address.');

        new Members(new FixedMember('member:ann@example.org'))->current();
    }

    public function testTheCalendarsOfTheConfigurationAreBuiltAndThoseNotPublishedLeftOut(): void
    {
        $calendars = new ConfiguredCalendars([
            'advent-2026' => Fixtures::config(),
            'draft' => Fixtures::config(published: false),
            'catch-up' => Fixtures::config('catch_up', 3),
            'same-day' => Fixtures::config('same_day'),
        ]);

        self::assertSame(['advent-2026', 'catch-up', 'same-day'], $calendars->ids());
        self::assertNull($calendars->find('draft'));
        self::assertNull($calendars->find('summer'));

        $entry = $calendars->find('advent-2026');
        self::assertInstanceOf(CalendarEntry::class, $entry);
        self::assertSame('Advent 2026', $entry->title);
        self::assertSame('advent-2026', $entry->calendar->id);
        self::assertSame(4, $entry->calendar->days());
        self::assertSame(PolicyKind::UntilClosing, $entry->calendar->policy->kind);
        self::assertEquals(Surprise::of(SurpriseKind::Code, 'gifts'), $entry->calendar->surprise(3));
        self::assertSame(20, $entry->calendar->surprise(2)->quantity);
        self::assertSame(3, $calendars->find('catch-up')?->calendar->policy->catchUpDays);
        self::assertSame(PolicyKind::SameDay, $calendars->find('same-day')?->calendar->policy->kind);
        self::assertSame([], new ConfiguredCalendars()->ids());
    }

    public function testACalendarOfTheConfigurationIsCheckedByTheLibrary(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('A catch-up lasts 1 to 31 days; 0 is not one.');

        ConfiguredCalendars::calendar('advent', Fixtures::config('catch_up'));
    }

    public function testTheFirstCatalogueThatHoldsACalendarAnswers(): void
    {
        $first = new ConfiguredCalendars(['advent-2026' => Fixtures::config(), 'a' => Fixtures::config()]);
        $second = new ConfiguredCalendars(['advent-2026' => [...Fixtures::config(), 'title' => 'Hidden'], 'b' => Fixtures::config()]);
        $calendars = new Calendars([$first, $second]);

        self::assertSame('Advent 2026', $calendars->find('advent-2026')?->title);
        self::assertSame('b', $calendars->find('b')?->calendar->id);
        self::assertNull($calendars->find('c'));
        self::assertSame(['advent-2026', 'a', 'b'], $calendars->ids());
        self::assertSame([], new Calendars([])->ids());
    }

    public function testACelebrationIsGivenOnceForTheBoxABoxOpenedTold(): void
    {
        $celebrations = new Celebrations();
        $member = ResourceReference::of('member', '44');
        new CelebrationListener($celebrations)(new BoxOpened('advent-2026', $member, 3, Surprise::points(10), new DateTimeImmutable()));

        self::assertFalse($celebrations->take('advent-2026', 'member:44', 2));
        self::assertFalse($celebrations->take('advent-2026', 'member:45', 3));
        self::assertFalse($celebrations->take('other', 'member:44', 3));
        self::assertTrue($celebrations->take('advent-2026', 'member:44', 3));
        self::assertFalse($celebrations->take('advent-2026', 'member:44', 3));

        $celebrations->record(new BoxOpened('advent-2026', $member, 4, Surprise::points(10), new DateTimeImmutable()));
        $celebrations->reset();

        self::assertFalse($celebrations->take('advent-2026', 'member:44', 4));
    }

    public function testAVisitorSeesTheBoxesByTheirWindowAndNoSurprise(): void
    {
        $boxes = VisitorBoxes::of(Fixtures::advent(OpeningPolicy::sameDay()), new DateTimeImmutable('2026-12-02 12:00', new DateTimeZone(Fixtures::ZONE)));

        self::assertSame([BoxState::Missed, BoxState::Openable, BoxState::Locked, BoxState::Locked], array_map(static fn (BoxView $box): BoxState => $box->state, $boxes));
        self::assertSame([1, 2, 3, 4], array_map(static fn (BoxView $box): int => $box->day, $boxes));
        self::assertSame('2026-12-03', $boxes[2]->date);
        self::assertSame('2026-12-02T00:00:00-05:00', $boxes[1]->window->from->format(\DATE_ATOM));
        self::assertNull($boxes[1]->surprise);
        self::assertNull($boxes[1]->openedAt);
    }
}
