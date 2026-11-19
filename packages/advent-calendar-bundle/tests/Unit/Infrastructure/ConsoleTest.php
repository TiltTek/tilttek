<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendarBundle\Application\CalendarEntry;
use Tilttek\AdventCalendarBundle\Application\ConfiguredCalendars;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ListCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\Policies;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\Calendars;

/**
 * The commands without a kernel: a site with no published calendar, and the words of each policy.
 */
#[CoversClass(ListCalendarsCommand::class)]
#[CoversClass(Policies::class)]
final class ConsoleTest extends TestCase
{
    public function testWithoutAPublishedCalendarTheListSaysSo(): void
    {
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, new ListCalendarsCommand(new ConfiguredCalendars(['draft' => Calendars::config(published: false)]))(new SymfonyStyle(new ArrayInput([]), $output)));
        $written = $output->fetch();
        self::assertStringContainsString('No calendar is published.', $written);
        self::assertStringNotContainsString('First date', $written);
    }

    public function testTheListShowsEachCalendarThatIsFoundAndSkipsTheOthers(): void
    {
        $catalogue = new class implements CalendarCatalogue {
            public function find(string $id): ?CalendarEntry
            {
                return 'advent-2026' === $id ? new CalendarEntry(Calendars::advent(OpeningPolicy::catchUp(2)), 'Advent 2026') : null;
            }

            public function ids(): array
            {
                return ['ghost', 'advent-2026', 'another-ghost'];
            }
        };
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, new ListCalendarsCommand($catalogue)(new SymfonyStyle(new ArrayInput([]), $output)));
        $written = $output->fetch();
        self::assertStringNotContainsString('No calendar is published.', $written);
        self::assertMatchesRegularExpression('/ Calendar +Title +Timezone +Boxes +First date +Closing date +Policy /', $written);
        self::assertStringContainsString('advent-2026', $written);
        self::assertStringContainsString('Advent 2026', $written);
        self::assertStringContainsString(Calendars::ZONE, $written);
        self::assertStringContainsString('2026-12-01', $written);
        self::assertStringContainsString('2026-12-04', $written);
        self::assertStringContainsString('openable on its day and 2 more', $written);
        self::assertStringNotContainsString('ghost', $written);
    }

    public function testEachPolicyIsSaidInWords(): void
    {
        self::assertSame('openable until closing', Policies::describe(OpeningPolicy::untilClosing()));
        self::assertSame('openable on its day only', Policies::describe(OpeningPolicy::sameDay()));
        self::assertSame('openable on its day and 3 more', Policies::describe(OpeningPolicy::catchUp(3)));
    }
}
