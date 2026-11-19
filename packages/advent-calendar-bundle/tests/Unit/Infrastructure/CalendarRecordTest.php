<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Infrastructure;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\PolicyKind;
use Tilttek\AdventCalendarBundle\Domain\Model\CalendarRecord;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\Calendars;

#[CoversClass(CalendarRecord::class)]
final class CalendarRecordTest extends TestCase
{
    public function testARecordIsTheCalendarItWasMadeFrom(): void
    {
        $calendar = Calendars::advent(OpeningPolicy::catchUp(2));
        $record = CalendarRecord::fromCalendar($calendar, 'Advent');

        self::assertSame('advent-2026', $record->id);
        self::assertSame('Advent', $record->title);
        self::assertSame(Calendars::ZONE, $record->timezone);
        self::assertSame('2026-12-01', $record->firstDate);
        self::assertSame('2026-12-04', $record->closingDate);
        self::assertSame(PolicyKind::CatchUp, $record->policy);
        self::assertSame(2, $record->catchUpDays);
        self::assertTrue($record->published);
        self::assertSame(['kind' => 'badge', 'reference' => 'advent-week', 'quantity' => 1], $record->surprises[3]);
        self::assertEquals($calendar, $record->toCalendar());
        self::assertFalse(CalendarRecord::fromCalendar($calendar, 'Advent', false)->published);
    }

    public function testANewRecordIsEmptyAndUnpublished(): void
    {
        $record = new CalendarRecord();

        self::assertSame('', $record->id);
        self::assertSame('UTC', $record->timezone);
        self::assertNull($record->closingDate);
        self::assertSame(PolicyKind::UntilClosing, $record->policy);
        self::assertSame(0, $record->catchUpDays);
        self::assertFalse($record->published);
        self::assertSame([], $record->surprises);
    }

    public function testTheColumnsAreMappedAsTheSchemaExpectsThem(): void
    {
        $columns = [];

        foreach (new ReflectionClass(CalendarRecord::class)->getProperties() as $parameter) {
            foreach ($parameter->getAttributes(ORM\Column::class) as $attribute) {
                $column = $attribute->newInstance();
                $columns[$parameter->getName()] = [$column->name, $column->length, $column->nullable, $column->type];
            }
        }

        self::assertSame([
            'id' => ['id', 64, false, null],
            'title' => ['title', 255, false, null],
            'timezone' => ['timezone', 64, false, null],
            'firstDate' => ['first_date', 10, false, null],
            'closingDate' => ['closing_date', 10, true, null],
            'policy' => ['policy', 16, false, null],
            'catchUpDays' => ['catch_up_days', null, false, 'smallint'],
            'published' => ['published', null, false, null],
            'surprises' => ['surprises', null, false, 'json'],
        ], $columns);
    }

    public function testTheSurprisesAreReadByTheirPositionNotTheirKey(): void
    {
        $record = CalendarRecord::fromCalendar(Calendars::advent(), 'Advent');
        $record->surprises = [7 => $record->surprises[0], 9 => $record->surprises[3]];

        self::assertSame(2, $record->toCalendar()->days());
        self::assertSame('points', $record->toCalendar()->surprise(1)->kind->value);
        self::assertSame('badge', $record->toCalendar()->surprise(2)->kind->value);
    }

    public function testEachPolicyIsReadBack(): void
    {
        self::assertSame(PolicyKind::SameDay, CalendarRecord::policyOf(PolicyKind::SameDay, 5)->kind);
        self::assertSame(0, CalendarRecord::policyOf(PolicyKind::SameDay, 5)->catchUpDays);
        self::assertSame(PolicyKind::UntilClosing, CalendarRecord::policyOf(PolicyKind::UntilClosing, 5)->kind);
        self::assertSame(5, CalendarRecord::policyOf(PolicyKind::CatchUp, 5)->catchUpDays);
    }

    public function testAnUnknownKindOfSurpriseIsRefused(): void
    {
        $record = CalendarRecord::fromCalendar(Calendars::advent(), 'Advent');
        $record->surprises[1]['kind'] = 'gold';

        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('"gold" is not a kind of surprise.');

        $record->toCalendar();
    }
}
