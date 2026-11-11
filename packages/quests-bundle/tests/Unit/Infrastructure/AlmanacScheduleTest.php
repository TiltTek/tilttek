<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Almanac\Almanac;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\RuleParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\QuestsBundle\Infrastructure\Almanac\AlmanacSchedule;
use Tilttek\QuestsBundle\Infrastructure\Almanac\AlmanacScheduleReader;

#[CoversClass(AlmanacSchedule::class)]
#[CoversClass(AlmanacScheduleReader::class)]
final class AlmanacScheduleTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(Almanac::class)) {
            self::markTestSkipped('phil/almanac is not installed: quests that come back at computed dates are an optional integration.');
        }
    }

    public function testAQuestComesBackAtTheDatesTheAlmanacComputes(): void
    {
        $toronto = new DateTimeZone('America/Toronto');
        $easter = new AlmanacSchedule(new Definition('quest', 'Quest', RuleParser::parse('easter - 2 days'), RuleParser::parse('easter + 1 day')), $toronto);

        self::assertEquals(
            new Occurrence('2027-03-26', new DateTimeImmutable('2027-03-26 00:00', $toronto), new DateTimeImmutable('2027-03-30 00:00', $toronto)),
            $easter->occurrenceAt(new DateTimeImmutable('2027-03-26 04:00 UTC')),
        );
        self::assertSame('2027-03-26', $easter->occurrenceAt(new DateTimeImmutable('2027-03-30 03:59 UTC'))?->code);
        self::assertNull($easter->occurrenceAt(new DateTimeImmutable('2027-03-30 04:00 UTC')));
        self::assertNull($easter->occurrenceAt(new DateTimeImmutable('2027-03-26 03:59 UTC')));
        self::assertSame('2028-04-14', $easter->occurrenceAt(new DateTimeImmutable('2028-04-15 12:00 UTC'))?->code);
        self::assertNull($easter->occurrenceAt(new DateTimeImmutable('1500-04-01 12:00 UTC')));
    }

    public function testTheReaderReadsTheRecurringDates(): void
    {
        $reader = new AlmanacScheduleReader();
        $utc = new DateTimeZone('UTC');

        self::assertSame(['recurs'], $reader->keys());
        self::assertNull($reader->read(['steps' => []], $utc));

        $week = $reader->read(['recurs' => ['from' => '10-25', 'days' => 7]], $utc);
        self::assertSame('2026-10-25', $week?->occurrenceAt(new DateTimeImmutable('2026-10-31 23:59 UTC'))?->code);
        self::assertNull($week->occurrenceAt(new DateTimeImmutable('2026-11-01 00:00 UTC')));
        self::assertNull($reader->read(['recurs' => ['from' => '10-25']], $utc)?->occurrenceAt(new DateTimeImmutable('2026-10-26 00:00 UTC')));
        self::assertSame('2026-12-21', $reader->read(['recurs' => ['from' => '12-21', 'until' => '03-20']], $utc)?->occurrenceAt(new DateTimeImmutable('2027-02-01 UTC'))?->code);

        $quest = new QuestReader('UTC', [$reader])->read('halloween', ['recurs' => ['from' => '10-31'], 'steps' => [['verbs' => ['costume.worn']]]]);
        self::assertInstanceOf(AlmanacSchedule::class, $quest->schedule);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function wrongRecurrences(): iterable
    {
        yield 'not a list' => ['10-25', 'A quest that comes back recurs from a rule, until another rule or for so many days.'];
        yield 'no start' => [['days' => 3], 'A quest that comes back recurs from a rule, until another rule or for so many days.'];
        yield 'a start that is not text' => [['from' => 3], 'A quest that comes back recurs from a rule, until another rule or for so many days.'];
        yield 'an unknown key' => [['from' => '10-25', 'for' => 3], 'A quest that comes back recurs from a rule, until another rule or for so many days.'];
        yield 'an end that is not text' => [['from' => '10-25', 'until' => 3], 'A quest that comes back lasts until a rule, or for a number of days.'];
        yield 'days that are not a number' => [['from' => '10-25', 'days' => '3'], 'A quest that comes back lasts until a rule, or for a number of days.'];
        yield 'a rule that is none' => [['from' => 'whenever'], '"whenever"'];
        yield 'both an end and days' => [['from' => '10-25', 'until' => '11-01', 'days' => 3], 'lasts 1 to 366 days, or until another rule; not both.'];
    }

    #[DataProvider('wrongRecurrences')]
    public function testAWrongRecurrenceIsRefused(mixed $recurs, string $message): void
    {
        $this->expectException(InvalidQuest::class);
        $this->expectExceptionMessageIsOrContains($message);

        new AlmanacScheduleReader()->read(['recurs' => $recurs], new DateTimeZone('UTC'));
    }
}
