<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Functional;

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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Daily\Daily;
use Tilttek\Collections\Pack\Packs;
use Tilttek\Collections\Source;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\CollectionsTables;
use Tilttek\CollectionsBundle\Tests\Functional\App\Recorder;
use Tilttek\CollectionsBundle\Tests\Functional\App\TestKernel;
use Tilttek\CollectionsBundle\TilttekCollectionsBundle;
use Twig\Environment;

/**
 * The bundle in an application: the tables in the schema the ORM generates, the item of the day given live by the
 * journal's projections — on a fixed date and on a date the almanac computes when it is installed —, the copies given
 * and the sets completed announced as events, a pack committed, opened and checked, the album, the item of the day and
 * the odds rendered by their templates, the commands, and a member exported and erased through the privacy bundle when
 * it is installed.
 */
#[CoversClass(TilttekCollectionsBundle::class)]
final class CollectionsBundleTest extends KernelTestCase
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

        foreach ([CollectionsTables::COPIES, CollectionsTables::COMPLETIONS, CollectionsTables::OPENINGS, CollectionsTables::CHOICES, CollectionsTables::DAILY, CollectionsTables::DEFINITIONS] as $table) {
            self::assertTrue($schema->hasTable($table));
        }

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testTheActivityGivesTheItemOfTheDayAndCompletesASet(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $collections = $this->service(Collections::class, Collections::class);
        $member = ResourceReference::of('member', '1');

        $journal->record(Activity::of('v1', 'member:1', 'page.viewed', new DateTimeImmutable('2026-11-07 15:00:00 UTC')));
        $journal->record(Activity::of('v2', 'member:1', 'page.viewed', new DateTimeImmutable('2026-11-07 16:00:00 UTC')));
        $journal->record(Activity::of('v3', 'member:1', 'page.viewed', new DateTimeImmutable('2027-03-28 16:00:00 UTC')));
        self::assertSame(['pirate', 'deckhand'], array_map(static fn (Copy $copy): string => $copy->item, $collections->copiesOf($member)));
        self::assertSame(['granted pirate to member:1', 'granted deckhand to member:1', 'completed crew by member:1: points xp 50'], $this->service(Recorder::class, Recorder::class)->events());

        $twig = $this->service('twig', Environment::class);
        $album = $twig->render('@TilttekCollections/album.html.twig', ['album' => $collections->album($member), 'locale' => 'fr']);
        self::assertStringContainsString('tilttek-collections-album__set--complete" data-set="crew"', $album);
        self::assertStringContainsString('L&#039;équipage', $album);
        self::assertStringContainsString('2 sur 2', $album);
        self::assertStringContainsString('Matelot', $album);
        self::assertStringContainsString('<img class="tilttek-collections-album__image" src="/img/deckhand.webp"', $album);
        self::assertStringContainsString('À trouver', $album);

        $collections->grant($member, 'pirate', Source::Gift, 'gift:1');
        $english = $twig->render('@TilttekCollections/album.html.twig', ['album' => $collections->album($member)]);
        self::assertStringContainsString('+1 double(s)', $english);
        self::assertStringContainsString('To find', $english);

        $daily = $twig->render('@TilttekCollections/daily.html.twig', ['pick' => $this->service(Daily::class, Daily::class)->today()]);
        self::assertStringContainsString('data-item="pirate" data-day="2026-11-07"', $daily);
        self::assertStringContainsString('Item of the day', $daily);
        self::assertStringContainsString('Sails the seas.', $daily);

        $odds = $twig->render('@TilttekCollections/odds.html.twig', ['odds' => $this->service(Packs::class, Packs::class)->odds('booster'), 'locale' => 'fr']);
        self::assertStringContainsString('<caption>Probabilités</caption>', $odds);
        self::assertStringContainsString('<td>Matelot</td>', $odds);
        self::assertStringContainsString('<td>75 %</td>', $odds);
        self::assertStringContainsString('<td>25 %</td>', $odds);
    }

    public function testAPackIsOpenedAndChecked(): void
    {
        $packs = $this->service(Packs::class, Packs::class);
        $opening = $packs->open($packs->commit(ResourceReference::of('member', '2'), 'booster', 'shop:1'), 'my lucky words');

        self::assertCount(2, $opening->items);
        self::assertTrue($packs->verify($opening));
        self::assertEquals($opening, $packs->openingsOf(ResourceReference::of('member', '2'))[0] ?? null);
    }

    public function testTheCommands(): void
    {
        $application = new Application(self::$kernel ?? throw new LogicException());

        $daily = new CommandTester($application->find('tilttek:collections:daily'));
        self::assertSame(0, $daily->execute(['--from' => '2026-11-07', '--until' => '2026-11-08']));
        self::assertStringContainsString('pirate', $daily->getDisplay());

        $grant = new CommandTester($application->find('tilttek:collections:grant'));
        self::assertSame(0, $grant->execute(['member' => 'member:3', 'item' => 'ember-d6', '--key' => 'contest:1']));

        $album = new CommandTester($application->find('tilttek:collections:album'));
        self::assertSame(0, $album->execute(['member' => 'member:3']));
        self::assertStringContainsString('ember-d6', $album->getDisplay());
    }

    public function testAMemberIsExportedAndErasedWithTheirAccount(): void
    {
        if (!class_exists(SubjectExport::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $collections = $this->service(Collections::class, Collections::class);
        $collections->welcome(ResourceReference::of('member', '7'), 'welcome:7');
        $collections->welcome(ResourceReference::of('member', '8'), 'welcome:8');

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('7'));
        $copies = $export['tilttek_collections']['copies'] ?? null;
        self::assertIsArray($copies);
        self::assertSame(['welcome:7#ember#ember-d6'], array_column($copies, 'key'));

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('7'));
        self::assertSame([], $collections->copiesOf(ResourceReference::of('member', '7')));
        self::assertCount(1, $collections->copiesOf(ResourceReference::of('member', '8')));
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
