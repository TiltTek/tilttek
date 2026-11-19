<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Infrastructure;

use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendarBundle\Infrastructure\Memory\InMemoryOpenings;
use Tilttek\AdventCalendarBundle\Infrastructure\Privacy\AdventCalendarPrivacy;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\AdventCalendarExtension;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\KitComponents;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\Calendars;

/**
 * What the bundle gives other packages: a member's openings to `phil/privacy-bundle`, and the names of the kit to Twig.
 */
#[CoversClass(AdventCalendarPrivacy::class)]
#[CoversClass(AdventCalendarExtension::class)]
final class IntegrationsTest extends TestCase
{
    public function testAMembersOpeningsAreExportedAndErased(): void
    {
        $openings = new InMemoryOpenings();
        $advent = new AdventCalendar($openings, new MockClock('2026-12-03 10:00:00 America/Toronto'), new EventDispatcher());
        $privacy = new AdventCalendarPrivacy($advent, $openings, 'member');
        $subject = DataSubject::fromString('44');

        self::assertSame('tilttek_advent_calendar', $privacy->module()->value);
        self::assertSame([], $privacy->export($subject));

        $advent->open(Calendars::advent(), ResourceReference::of('member', '44'), 2);
        $advent->open(Calendars::advent(id: 'a-first'), ResourceReference::of('member', '44'), 1);
        $advent->open(Calendars::advent(), ResourceReference::of('member', '45'), 1);

        self::assertSame(['openings' => [
            ['calendar' => 'a-first', 'member' => 'member:44', 'day' => 1, 'opened_at' => '2026-12-03T15:00:00+00:00'],
            ['calendar' => 'advent-2026', 'member' => 'member:44', 'day' => 2, 'opened_at' => '2026-12-03T15:00:00+00:00'],
        ]], $privacy->export($subject));
        self::assertSame([], new AdventCalendarPrivacy($advent, $openings, 'player')->export($subject));

        $report = $privacy->erase($subject);

        self::assertSame('tilttek_advent_calendar', $report->module->value);
        self::assertSame(['tilttek_advent_calendar_opening' => ErasureEffect::Deleted], $report->effects);
        self::assertSame([], $privacy->export($subject));
        self::assertCount(1, $openings->ofMember(ResourceReference::of('member', '45')));
    }

    public function testTheTwigFunctionNamesAComponentOfTheKit(): void
    {
        self::assertSame('Card', new AdventCalendarExtension()->kit('Card'));
        self::assertSame('ui:Card:Title', new AdventCalendarExtension(new KitComponents('ui'))->kit('Card:Title'));
    }
}
