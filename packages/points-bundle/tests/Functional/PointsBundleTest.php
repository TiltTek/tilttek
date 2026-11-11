<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
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
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Points;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsTables;
use Tilttek\PointsBundle\Tests\Functional\App\TestKernel;
use Tilttek\PointsBundle\TilttekPointsBundle;

/**
 * The bundle in an application: the tables in the schema the ORM generates, the activity turned into points live by
 * the journal's projections, a cap, a cancellation, a spending refused then made, the credits due expired by their
 * command, a member's history, and a member exported and erased through the privacy bundle when it is installed.
 */
#[CoversClass(TilttekPointsBundle::class)]
final class PointsBundleTest extends KernelTestCase
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
        self::assertTrue($schema->hasTable(PointsTables::MOVEMENTS));
        self::assertTrue($schema->hasTable(PointsTables::LOCKS));

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testTheActivityEarnsPointsThatAreSpentAndExpire(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $points = $this->service(Points::class, Points::class);
        $member = ResourceReference::of('member', '1');

        $journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), 'game:1'));
        $journal->record(Activity::of('game:2:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-05 20:00:00 UTC'), 'game:2'));
        $journal->record(Activity::of('news:3:member:1', 'member:1', 'news.commented', new DateTimeImmutable('2026-11-06 21:00:00 UTC'), 'news:3'));
        self::assertSame(15, $points->balance($member));
        self::assertSame(2, $points->balance($member, 'stars'));

        $journal->cancel('game:2:member:1');
        self::assertSame(10, $points->balance($member));

        try {
            $points->spend($member, 11, 'order:1', 'order:1');
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints) {
            self::assertSame(10, $points->balance($member));
        }

        $points->spend($member, 10, 'order:1', 'order:1');
        self::assertSame(0, $points->balance($member));

        $tester = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:points:history'));
        self::assertSame(0, $tester->execute(['member' => 'member:1']));
        self::assertStringContainsString('Balance of member:1: 0.', $tester->getDisplay());
        self::assertStringContainsString('#reverse:#earn:games:game:2:member:1', $tester->getDisplay());

        $this->service('clock', MockClock::class)->modify('2026-12-07 03:00:00 UTC');
        $expire = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:points:expire'));
        self::assertSame(0, $expire->execute([]));
        self::assertSame('2 points expired.', trim($expire->getDisplay()));
        self::assertSame(0, $points->balance($member, 'stars'));
    }

    public function testAMemberIsExportedAndErasedWithTheirAccount(): void
    {
        if (!class_exists(SubjectExport::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $points = $this->service(Points::class, Points::class);
        $points->grant(ResourceReference::of('member', '7'), 10, 'welcome:7');
        $points->transfer(ResourceReference::of('member', '7'), ResourceReference::of('member', '8'), 4, 'gift:7');

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('7'));
        $movements = $export['tilttek_points']['points'] ?? null;
        self::assertIsArray($movements);
        self::assertSame(['welcome:7', 'gift:7'], array_column($movements, 'key'));

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('7'));
        self::assertSame([['gift:7', 'erased:member', 'member:member:8']], $this->service('doctrine.dbal.default_connection', Connection::class)->fetchAllNumeric('SELECT movement_key, debit, credit FROM tilttek_points_movement'));
        self::assertSame(4, $points->balance(ResourceReference::of('member', '8')));
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
