<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\AdventCalendar\BoxState;
use Tilttek\AdventCalendar\BoxView;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\Exception\AdventCalendarException;
use Tilttek\AdventCalendar\Exception\BoxNotOpenable;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\OpeningWindow;
use Tilttek\AdventCalendar\PolicyKind;
use Tilttek\AdventCalendar\Surprise;
use Tilttek\AdventCalendar\SurpriseKind;

#[CoversClass(Surprise::class)]
#[CoversClass(SurpriseKind::class)]
#[CoversClass(OpeningPolicy::class)]
#[CoversClass(PolicyKind::class)]
#[CoversClass(BoxOpened::class)]
#[CoversClass(BoxView::class)]
#[CoversClass(BoxState::class)]
#[CoversClass(Opening::class)]
#[CoversClass(BoxNotOpenable::class)]
#[CoversClass(InvalidCalendar::class)]
final class ValuesTest extends TestCase
{
    public function testTheSurprises(): void
    {
        self::assertSame(['kind' => 'points', 'reference' => 'points', 'quantity' => 50], Surprise::points(50)->toArray());
        self::assertSame(['kind' => 'points', 'reference' => 'stars', 'quantity' => 1], Surprise::points(1, 'stars')->toArray());
        self::assertSame(['kind' => 'badge', 'reference' => 'early-bird', 'quantity' => 1], Surprise::badge('early-bird')->toArray());
        self::assertSame(['kind' => 'content', 'reference' => 'page:42', 'quantity' => 1], Surprise::content(ResourceReference::of('page', '42'))->toArray());
        self::assertSame(['kind' => 'code', 'reference' => 'winter-discount', 'quantity' => 1], Surprise::code('winter-discount')->toArray());
        self::assertSame(Surprise::MAX_QUANTITY, Surprise::of(SurpriseKind::Points, 'p', 1_000_000)->quantity);
        self::assertSame(255, \strlen(Surprise::badge('a'.str_repeat(' ', 254))->reference));
        self::assertSame(1_000_000, Surprise::MAX_QUANTITY);
    }

    #[DataProvider('refusedSurprises')]
    public function testWhatASurpriseRefuses(string $reference, int $quantity, string $message): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains($message);

        Surprise::of(SurpriseKind::Badge, $reference, $quantity);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function refusedSurprises(): iterable
    {
        $reference = 'The reference of a surprise is 1 to 255 printable ASCII characters, starting with a non-space.';

        yield 'an empty reference' => ['', 1, $reference];
        yield 'a reference starting with a space' => [' a', 1, $reference];
        yield 'a reference of 256 characters' => [str_repeat('a', 256), 1, $reference];
        yield 'a control character' => ["a\tb", 1, $reference];
        yield 'a line feed at the end' => ["a\n", 1, $reference];
        yield 'a delete character' => ["a\x7f", 1, $reference];
        yield 'a letter outside ASCII' => ['café', 1, $reference];
        yield 'a quantity of 0' => ['a', 0, 'The quantity of a surprise is 1 to 1000000; 0 is not one.'];
        yield 'a quantity above the most' => ['a', 1_000_001, 'The quantity of a surprise is 1 to 1000000; 1000001 is not one.'];
    }

    public function testThePolicies(): void
    {
        self::assertSame([PolicyKind::UntilClosing, null], [OpeningPolicy::untilClosing()->kind, OpeningPolicy::untilClosing()->daysAfter()]);
        self::assertSame([PolicyKind::SameDay, 0], [OpeningPolicy::sameDay()->kind, OpeningPolicy::sameDay()->daysAfter()]);
        self::assertSame([0, 0], [OpeningPolicy::untilClosing()->catchUpDays, OpeningPolicy::sameDay()->catchUpDays]);
        self::assertSame([PolicyKind::CatchUp, 1, 1], [OpeningPolicy::catchUp(1)->kind, OpeningPolicy::catchUp(1)->catchUpDays, OpeningPolicy::catchUp(1)->daysAfter()]);
        self::assertSame(31, OpeningPolicy::catchUp(31)->daysAfter());
        self::assertSame(31, OpeningPolicy::MAX_CATCH_UP_DAYS);
    }

    #[DataProvider('refusedCatchUps')]
    public function testWhatACatchUpRefuses(int $days): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A catch-up lasts 1 to 31 days; %d is not one.', $days));

        OpeningPolicy::catchUp($days);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedCatchUps(): iterable
    {
        yield 'none' => [0];
        yield 'too many' => [32];
    }

    public function testTheEventAndTheOpening(): void
    {
        $member = ResourceReference::of('member', '7');
        $at = new DateTimeImmutable('2026-12-03 08:00:00 UTC');
        $event = new BoxOpened('advent-2026', $member, 3, Surprise::badge('a'), $at);

        self::assertSame('advent:advent-2026:member:7:3', $event->idempotencyKey());
        self::assertSame(['calendar' => 'advent-2026', 'member' => 'member:7', 'day' => 3, 'opened_at' => '2026-12-03T08:00:00+00:00'], new Opening('advent-2026', $member, 3, $at)->toArray());
    }

    public function testAView(): void
    {
        $window = new OpeningWindow(new DateTimeImmutable('2026-12-01 00:00 UTC'), new DateTimeImmutable('2026-12-02 00:00 UTC'));

        self::assertTrue(new BoxView(1, '2026-12-01', BoxState::Openable, $window)->isOpenable());
        self::assertFalse(new BoxView(1, '2026-12-01', BoxState::Locked, $window)->isOpenable());
        self::assertFalse(new BoxView(1, '2026-12-01', BoxState::Opened, $window)->isOpenable());
        self::assertFalse(new BoxView(1, '2026-12-01', BoxState::Missed, $window)->isOpenable());
    }

    public function testTheExceptions(): void
    {
        $at = new DateTimeImmutable('2026-12-03 00:00:00+01:00');

        self::assertSame('The box 3 of the calendar "advent" opens at 2026-12-03T00:00:00+01:00.', BoxNotOpenable::notYet('advent', 3, $at)->getMessage());
        self::assertSame('The box 3 of the calendar "advent" could be opened until 2026-12-03T00:00:00+01:00.', BoxNotOpenable::missed('advent', 3, $at)->getMessage());
        self::assertInstanceOf(AdventCalendarException::class, BoxNotOpenable::missed('advent', 3, $at));
        self::assertInstanceOf(AdventCalendarException::class, InvalidCalendar::because('x'));
    }
}
