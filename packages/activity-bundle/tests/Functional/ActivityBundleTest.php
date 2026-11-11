<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Exception\UnknownVerb;
use Tilttek\Activity\Journal;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivityTables;
use Tilttek\ActivityBundle\Tests\Functional\App\GamesPlayed;
use Tilttek\ActivityBundle\Tests\Functional\App\TestKernel;
use Tilttek\ActivityBundle\TilttekActivityBundle;

/**
 * The bundle in an application: the tables in the schema the ORM generates, an activity recorded once in SQLite, the
 * projection kept up to date live and rebuilt by its command, an undeclared verb refused, and a member exported and
 * erased through the privacy bundle when it is installed.
 */
#[CoversClass(TilttekActivityBundle::class)]
final class ActivityBundleTest extends KernelTestCase
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
        $schemaTool = new SchemaTool($entityManager);
        $schema = $schemaTool->getSchemaFromMetadata([]);
        self::assertTrue($schema->hasTable(ActivityTables::ENTRIES));

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testAnActivityIsRecordedOnceAndProjectedLive(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $games = $this->service(GamesPlayed::class, GamesPlayed::class);

        self::assertTrue($journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-06 20:00:00 UTC'), 'game:1'))->new);
        self::assertFalse($journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-06 20:00:00 UTC'), 'game:1'))->new);
        $journal->record(Activity::of('news:3:member:1', 'member:1', 'news.commented', new DateTimeImmutable('2026-11-06 21:00:00 UTC'), 'news:3'));
        self::assertSame(['member:1' => 1], $games->games);
        self::assertSame('2026-11-07 03:00:00', $this->service('doctrine.dbal.default_connection', Connection::class)->fetchOne('SELECT recorded_at FROM tilttek_activity_entry WHERE position = 1'));
        self::assertSame(1, $this->service('doctrine.dbal.default_connection', Connection::class)->fetchOne("SELECT position FROM tilttek_activity_checkpoint WHERE projection = 'games_played'"));

        $journal->cancel('game:1:member:1');
        self::assertSame(['member:1' => 0], $games->games);

        $tester = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:activity:rebuild'));
        self::assertSame(0, $tester->execute(['projection' => 'games_played']));
        self::assertSame(['member:1' => 0], $games->games);

        $this->expectException(UnknownVerb::class);
        $journal->record(Activity::of('k', 'member:1', 'order.paid', new DateTimeImmutable()));
    }

    public function testAMemberIsExportedAndErasedWithTheirAccount(): void
    {
        if (!class_exists(SubjectExport::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $journal = $this->service(Journal::class, Journal::class);
        $journal->record(Activity::of('a', 'member:7', 'game.played', new DateTimeImmutable('2026-11-06 20:00:00 UTC')));
        $journal->record(Activity::of('b', 'member:8', 'game.played', new DateTimeImmutable('2026-11-06 20:00:00 UTC'), 'member:7'));

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('7'));
        self::assertSame(['tilttek_activity' => ['activity' => [['position' => 1, 'kind' => 'recorded', 'written_at' => '2026-11-07T03:00:00+00:00', 'key' => 'a', 'member' => 'member:7', 'verb' => 'game.played', 'occurred_at' => '2026-11-06T20:00:00+00:00', 'object' => null, 'quantity' => 1, 'context' => []]]]], $export);

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('7'));
        self::assertSame([['b', null]], $this->service('doctrine.dbal.default_connection', Connection::class)->fetchAllNumeric('SELECT idempotency_key, object FROM tilttek_activity_entry'));
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
