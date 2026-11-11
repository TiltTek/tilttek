<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\Progression\Progression;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\ProgressionTables;
use Tilttek\ProgressionBundle\Tests\Functional\App\Recorder;
use Tilttek\ProgressionBundle\Tests\Functional\App\TestKernel;
use Tilttek\ProgressionBundle\TilttekProgressionBundle;
use Twig\Environment;

/**
 * The bundle in an application: the tables in the schema the ORM generates, the activity turned into experience and
 * streaks live by the journal's projections and announced as events, a cancellation, the progress bar and the streak
 * rendered by their templates, the sweep and the show commands, and a member exported and erased through the privacy
 * bundle.
 */
#[CoversClass(TilttekProgressionBundle::class)]
final class ProgressionBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata([]);

        foreach ([ProgressionTables::GAINS, ProgressionTables::MILESTONES, ProgressionTables::GESTURES, ProgressionTables::FREEZES, ProgressionTables::STATES] as $table) {
            self::assertTrue($schema->hasTable($table));
        }

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testTheActivityLevelsUpAndKeepsAStreak(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $progression = $this->service(Progression::class, Progression::class);
        $recorder = $this->service(Recorder::class, Recorder::class);
        $member = ResourceReference::of('member', '1');

        $journal->record(Activity::of('g1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), context: ['timezone' => 'Europe/Paris']));
        $journal->record(Activity::of('g2', 'member:1', 'game.played', new DateTimeImmutable('2026-11-05 20:00:00 UTC'), context: ['timezone' => 'Europe/Paris']));
        $journal->record(Activity::of('g3', 'member:1', 'game.played', new DateTimeImmutable('2026-11-06 20:00:00 UTC'), context: ['timezone' => 'Europe/Paris']));
        $journal->record(Activity::of('w1', 'member:1', 'game.won', new DateTimeImmutable('2026-11-06 20:00:00 UTC'), quantity: 5));

        self::assertSame(120, $progression->standing($member, 'player')->lifetime);
        self::assertSame(2, $progression->standing($member, 'player')->level->number);
        self::assertSame('2026-11', $progression->standing($member, 'monthly')->season?->code);
        self::assertSame(3, $progression->streak($member, 'daily-play')->current);
        self::assertSame(['up player 1→2', 'milestone veteran', 'up monthly 1→2'], $recorder->events());

        $journal->cancel('g3');
        self::assertSame(['up player 1→2', 'milestone veteran', 'up monthly 1→2', 'down player 2→1'], $recorder->events());
        $journal->record(Activity::of('g4', 'member:1', 'game.played', new DateTimeImmutable('2026-11-06 22:00:00 UTC'), context: ['timezone' => 'Europe/Paris']));
        self::assertSame(['up player 1→2', 'milestone veteran', 'up monthly 1→2', 'down player 2→1', 'up player 1→2'], $recorder->events());

        $twig = $this->service('twig', Environment::class);
        $bar = $twig->render('@TilttekProgression/bar.html.twig', ['standing' => $progression->standing($member, 'player')]);
        self::assertStringContainsString('data-track="player"', $bar);
        self::assertStringContainsString('Level 2', $bar);
        self::assertStringContainsString('<progress max="100" value="20">20 %</progress>', $bar);
        self::assertStringContainsString('80 experience to level 3', $bar);
        $monthly = $twig->render('@TilttekProgression/bar.html.twig', ['standing' => $progression->standing($member, 'monthly')]);
        self::assertStringContainsString('Season 2026-11', $monthly);
        $streak = $twig->render('@TilttekProgression/streak.html.twig', ['streak' => $progression->streak($member, 'daily-play')]);
        self::assertStringContainsString('3 in a row', $streak);
        self::assertStringContainsString('Best: 3', $streak);
        self::assertStringContainsString('is-alive', $streak);

        $this->service('clock', MockClock::class)->modify('2026-11-09 12:00:00 UTC');
        $sweep = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:progression:sweep'));
        self::assertSame(0, $sweep->execute([]));
        self::assertSame('1 streaks broke.', trim($sweep->getDisplay()));
        self::assertSame(['up player 1→2', 'milestone veteran', 'up monthly 1→2', 'down player 2→1', 'up player 1→2', 'broken daily-play'], $recorder->events());

        $show = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:progression:show'));
        self::assertSame(0, $show->execute(['subject' => 'member:1']));
        self::assertStringContainsString('daily-play', $show->getDisplay());
    }

    public function testTheLastLevelFillsTheBar(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $journal->record(Activity::of('g1', 'member:2', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), quantity: 20));

        $bar = $this->service('twig', Environment::class)->render('@TilttekProgression/bar.html.twig', ['standing' => $this->service(Progression::class, Progression::class)->standing(ResourceReference::of('member', '2'), 'player')]);

        self::assertStringContainsString('<progress max="1" value="1">Last level reached</progress>', $bar);
        self::assertStringContainsString('Level 5', $bar);
    }

    public function testAMemberIsExportedAndErasedWithTheirAccount(): void
    {
        if (!class_exists(SubjectExport::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $journal = $this->service(Journal::class, Journal::class);
        $journal->record(Activity::of('g1', 'member:7', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), quantity: 3));
        $journal->record(Activity::of('g2', 'member:8', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC')));

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('7'));
        $gains = $export['tilttek_progression']['gains'] ?? null;
        self::assertIsArray($gains);
        self::assertSame(['g1'], array_column($gains, 'activity'));

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('7'));
        $progression = $this->service(Progression::class, Progression::class);
        self::assertSame(0, $progression->standing(ResourceReference::of('member', '7'), 'player')->lifetime);
        self::assertSame(40, $progression->standing(ResourceReference::of('member', '8'), 'player')->lifetime);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    private function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
