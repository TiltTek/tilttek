<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\Almanac\Almanac;
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
use Tilttek\Quests\Completion;
use Tilttek\Quests\Duel\Duels;
use Tilttek\Quests\Quests;
use Tilttek\Quests\QuestState;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\QuestsTables;
use Tilttek\QuestsBundle\Tests\Functional\App\Recorder;
use Tilttek\QuestsBundle\Tests\Functional\App\TestKernel;
use Tilttek\QuestsBundle\TilttekQuestsBundle;
use Twig\Environment;

/**
 * The bundle in an application: the tables in the schema the ORM generates, the activity turned into progress live by
 * the journal's projections, the quests accomplished and the duels won announced as events, a quest that comes back
 * at the dates the almanac computes when it is installed, the list and the detail rendered by their templates, the
 * commands, and a member exported and erased through the privacy bundle when it is installed.
 */
#[CoversClass(TilttekQuestsBundle::class)]
final class QuestsBundleTest extends KernelTestCase
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

        foreach ([QuestsTables::CONTRIBUTIONS, QuestsTables::ENROLMENTS, QuestsTables::COMPLETIONS, QuestsTables::DUELS, QuestsTables::DEFINITIONS] as $table) {
            self::assertTrue($schema->hasTable($table));
        }

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testTheActivityAccomplishesQuestsAndDecidesDuels(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $quests = $this->service(Quests::class, Quests::class);
        $duels = $this->service(Duels::class, Duels::class);
        $recorder = $this->service(Recorder::class, Recorder::class);
        $member = ResourceReference::of('member', '1');

        $duels->challenge('d1', 'race', $member, ResourceReference::of('member', '2'));
        $duels->accept('d1', ResourceReference::of('member', '2'));

        $journal->record(Activity::of('g1', 'member:1', 'game.won', new DateTimeImmutable('2026-11-07 04:00:00 UTC')));
        $journal->record(Activity::of('g2', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 05:00:00 UTC')));
        self::assertSame(QuestState::UnderWay, $quests->status($member, 'first-steps')->state);

        $journal->record(Activity::of('g3', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 06:00:00 UTC')));
        $journal->record(Activity::of('g4', 'member:1', 'game.won', new DateTimeImmutable('2026-11-07 07:00:00 UTC')));
        self::assertSame(['completed race for member:1: ', 'won d1', 'completed first-steps for member:1: points xp 50'], $recorder->events());

        $twig = $this->service('twig', Environment::class);
        $list = $twig->render('@TilttekQuests/list.html.twig', ['statuses' => $quests->board($member)]);
        self::assertStringContainsString('data-quest="first-steps"', $list);
        self::assertStringContainsString('Accomplished', $list);
        self::assertStringNotContainsString('data-quest="easter-hunt"', $list);
        $detail = $twig->render('@TilttekQuests/detail.html.twig', ['status' => $quests->status($member, 'first-steps')]);
        self::assertStringContainsString('<ol class="tilttek-quests-detail__steps">', $detail);
        self::assertStringContainsString('<progress max="2" value="2">100 %</progress>', $detail);
        self::assertStringContainsString('50 xp', $detail);
        self::assertStringContainsString('Accomplished on 2026-11-07', $detail);
        $empty = $twig->render('@TilttekQuests/list.html.twig', ['statuses' => []]);
        self::assertStringContainsString('No quest under way.', $empty);

        $list = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:quests:list'));
        self::assertSame(0, $list->execute([]));
        self::assertStringContainsString('easter-hunt', $list->getDisplay());
        $show = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:quests:show'));
        self::assertSame(0, $show->execute(['member' => 'member:1']));
        self::assertStringContainsString('completed', $show->getDisplay());
    }

    public function testAQuestComesBackAtTheDatesTheAlmanacComputes(): void
    {
        if (!class_exists(Almanac::class)) {
            self::markTestSkipped('phil/almanac is not installed: quests that come back at computed dates are an optional integration.');
        }

        $journal = $this->service(Journal::class, Journal::class);
        $member = ResourceReference::of('member', '3');

        foreach (['2027-03-26 12:00:00', '2027-03-27 12:00:00', '2027-03-29 12:00:00', '2027-04-02 12:00:00'] as $i => $at) {
            $journal->record(Activity::of('e'.$i, 'member:3', 'egg.found', new DateTimeImmutable($at.' UTC')));
        }

        $completions = $this->service(Quests::class, Quests::class)->completions($member);
        self::assertSame(['2027-03-26'], array_map(static fn (Completion $completion): string => $completion->occurrence, $completions));
        self::assertSame(['completed easter-hunt for member:3: '], $this->service(Recorder::class, Recorder::class)->events());
    }

    public function testADuelLeftUnansweredExpires(): void
    {
        $duels = $this->service(Duels::class, Duels::class);
        $duels->challenge('d2', 'race', ResourceReference::of('member', '4'), ResourceReference::of('member', '5'));
        $this->service('clock', MockClock::class)->modify('2026-11-15 12:00:00 UTC');

        $expire = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:quests:expire'));
        self::assertSame(0, $expire->execute([]));
        self::assertStringContainsString('1 duel(s) expired.', $expire->getDisplay());
        self::assertSame(['expired d2'], $this->service(Recorder::class, Recorder::class)->events());
    }

    public function testAMemberIsExportedAndErasedWithTheirAccount(): void
    {
        if (!class_exists(SubjectExport::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $journal = $this->service(Journal::class, Journal::class);
        $journal->record(Activity::of('g1', 'member:7', 'game.played', new DateTimeImmutable('2026-11-07 04:00:00 UTC')));
        $journal->record(Activity::of('g2', 'member:8', 'game.played', new DateTimeImmutable('2026-11-07 04:00:00 UTC')));

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('7'));
        $contributions = $export['tilttek_quests']['contributions'] ?? null;
        self::assertIsArray($contributions);
        self::assertSame(['g1'], array_column($contributions, 'activity'));

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('7'));
        $quests = $this->service(Quests::class, Quests::class);
        self::assertSame(0, $quests->status(ResourceReference::of('member', '7'), 'first-steps')->progress->steps[0]->value);
        self::assertSame(1, $quests->status(ResourceReference::of('member', '8'), 'first-steps')->progress->steps[0]->value);
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
