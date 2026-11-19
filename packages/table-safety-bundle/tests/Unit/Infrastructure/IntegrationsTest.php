<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ContentWarnings\BaseCatalogue;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\Store\InMemoryFeedbackStore;
use Tilttek\TableSafety\Store\InMemoryParticipationStore;
use Tilttek\TableSafety\Store\InMemorySignalStore;
use Tilttek\TableSafety\Store\InMemoryTableStore;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafetyBundle\Infrastructure\Console\PurgeSignalsCommand;
use Tilttek\TableSafetyBundle\Infrastructure\Privacy\TableSafetyPrivacy;

#[CoversClass(TableSafetyPrivacy::class)]
#[CoversClass(PurgeSignalsCommand::class)]
final class IntegrationsTest extends TestCase
{
    public function testAParticipantIsExportedAndErased(): void
    {
        $safety = new TableSafety(BaseCatalogue::load(), new InMemoryTableStore(), new InMemoryParticipationStore(), new InMemorySignalStore(), new InMemoryFeedbackStore());
        $privacy = new TableSafetyPrivacy($safety, 'member');
        $subject = DataSubject::fromString('ana');

        self::assertSame('tilttek_table_safety', $privacy->module()->value);
        self::assertSame([], $privacy->export($subject));

        $safety->answerLinesAndVeils(ResourceReference::of('table', '7'), ResourceReference::of('member', 'ana'), LinesAndVeils::none()->with('gore', Boundary::Line), new DateTimeImmutable('2026-11-12 20:00:00 UTC'));
        self::assertSame($safety->export(ResourceReference::of('member', 'ana')), $privacy->export($subject));
        self::assertArrayHasKey('participations', $privacy->export($subject));

        $report = $privacy->erase($subject);

        self::assertSame('tilttek_table_safety', $report->module->value);
        self::assertSame(['tilttek_table_safety_participation' => ErasureEffect::Deleted, 'tilttek_table_safety_feedback' => ErasureEffect::Deleted, 'tilttek_table_safety_signal' => ErasureEffect::Pseudonymised], $report->effects);
        self::assertSame([], $privacy->export($subject));
        self::assertSame([], new TableSafetyPrivacy($safety, 'player')->export(DataSubject::fromString('ana')));
    }

    public function testOldSignalsArePurged(): void
    {
        $signals = new InMemorySignalStore();
        $safety = new TableSafety(BaseCatalogue::load(), new InMemoryTableStore(), new InMemoryParticipationStore(), $signals, new InMemoryFeedbackStore());
        $table = ResourceReference::of('table', '7');
        $safety->raise($table, ResourceReference::of('member', 'ana'), SignalKind::XCard, false, new DateTimeImmutable('2026-10-01 20:00:00 UTC'));
        $safety->raise($table, ResourceReference::of('member', 'ana'), SignalKind::XCard, false, new DateTimeImmutable('2026-11-10 20:00:00 UTC'));

        $command = new PurgeSignalsCommand($signals, new MockClock('2026-11-12 20:00:00 UTC'), '30 days');

        $output = new BufferedOutput();
        self::assertSame(Command::SUCCESS, $command(new SymfonyStyle(new ArrayInput([]), $output)));
        self::assertStringContainsString('1 signals raised before 2026-10-13T20:00:00+00:00 deleted.', $output->fetch());
        self::assertCount(1, $signals->recent($table));

        self::assertSame(Command::SUCCESS, $command(new SymfonyStyle(new ArrayInput([]), $output), '1 day'));
        self::assertStringContainsString('1 signals raised before 2026-11-11T20:00:00+00:00 deleted.', $output->fetch());

        self::assertSame(Command::INVALID, $command(new SymfonyStyle(new ArrayInput([]), $output), 'yesterday'));
        self::assertStringContainsString('The retention is a number and a unit', $output->fetch());
    }
}
