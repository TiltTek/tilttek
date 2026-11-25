<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;
use Tilttek\LuckTokensBundle\Tests\Functional\App\HeardEvents;
use Tilttek\LuckTokensBundle\Tests\Functional\App\TestKernel;
use Tilttek\LuckTokensBundle\TilttekLuckTokensBundle;

/**
 * The bundle in an application: the ledger's table in the schema the ORM generates, tokens granted, spent and passed
 * under the locks, a session started, the events heard by a listener of the application, the entries kept in UTC, and
 * a member erased through the privacy bundle.
 */
#[CoversClass(TilttekLuckTokensBundle::class)]
final class LuckTokensBundleTest extends KernelTestCase
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
        self::assertTrue($schema->hasTable(LuckTokensTables::ENTRIES));
        self::assertFalse($schema->hasTable(LuckTokensTables::RULES));

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testTokensGrantedSpentPassedAndAMemberErased(): void
    {
        $tokens = $this->service(LockedLuckTokens::class, LockedLuckTokens::class);
        $reader = $this->service(LuckTokens::class, LuckTokens::class);
        $table = ResourceReference::of('table', '12');
        $gm = ResourceReference::of('member', '7');
        $ana = ResourceReference::of('member', '8');
        $bo = ResourceReference::of('member', '9');

        $tokens->grant($table, 'inspiration', $ana, 1, $gm, 'op-1', 'A clever plan');
        $tokens->transfer($table, 'inspiration', $ana, $bo, 1, $ana, 'op-2');
        $tokens->spend($table, 'inspiration', $bo, 1, $bo, 'op-3', 'Reroll');
        $tokens->startSession($table, 's-1', [$ana, $bo], $gm, 'op-4');

        self::assertSame([0, 3, 3], [$reader->balance($table, 'inspiration', $bo), $reader->balance($table, 'fate', $ana), $reader->balance($table, 'fate', $bo)]);
        self::assertSame('2026-11-14T00:30:00.250000Z', $reader->log($table)[0]->toArray()['at']);
        self::assertSame(['TokensGranted', 'TokensTransferred', 'TokensSpent', 'SessionStarted'], $this->service('app.heard', HeardEvents::class)->heard);
        self::assertInstanceOf(TokenLedger::class, self::getContainer()->get(TokenLedger::class));

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('8'));

        $connection = $this->service('doctrine.dbal.default_connection', Connection::class);
        self::assertSame([], $connection->fetchFirstColumn(\sprintf("SELECT position FROM %s WHERE holder = 'member:8' OR actor = 'member:8'", LuckTokensTables::ENTRIES)));
        self::assertSame(3, $reader->balance($table, 'fate', $reader->log($table)[1]->holder));
        self::assertSame('erased', $reader->log($table)[1]->holder->key);
        self::assertNull($reader->log($table)[0]->reason, 'The reason of an entry the member held is cleared.');
        self::assertSame('Reroll', $reader->log($table)[3]->reason);
        self::assertSame('HolderErased', $this->service('app.heard', HeardEvents::class)->heard[4]);
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
