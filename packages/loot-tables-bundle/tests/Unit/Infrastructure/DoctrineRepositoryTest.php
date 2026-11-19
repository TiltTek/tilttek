<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Doctrine\DoctrineLootDrawRepository;

/**
 * The draws on SQLite in memory, their table created from the mapping, each draw read back with the entity manager
 * cleared.
 */
#[CoversClass(DoctrineLootDrawRepository::class)]
#[CoversClass(LootDraw::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheTable(): void
    {
        $this->repository();
        $connection = $this->entityManager?->getConnection();
        self::assertNotNull($connection);

        $columns = array_column($connection->fetchAllAssociative(\sprintf('PRAGMA table_info(%s)', LootDraw::TABLE)), 'notnull', 'name');
        self::assertSame(['result', 'proof', 'drawn', 'revealed_at', 'id', 'order_number', 'line', 'option_code', 'unit', 'drawer', 'seed', 'commitment', 'texts', 'created_at'], array_keys($columns));
        self::assertSame([0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 0, 0, 0, 1], array_map(static fn (mixed $notNull): int => \is_int($notNull) ? $notNull : -1, array_values($columns)));
        $types = array_column($connection->fetchAllAssociative(\sprintf('PRAGMA table_info(%s)', LootDraw::TABLE)), 'type', 'name');
        self::assertSame(['VARCHAR(32)', 'VARCHAR(64)', 'VARCHAR(180)', 'VARCHAR(32)', 'INTEGER', 'VARCHAR(64)', 'VARCHAR(64)', 'VARCHAR(64)'], [$types['id'], $types['order_number'], $types['line'], $types['option_code'], $types['unit'], $types['drawer'], $types['seed'], $types['commitment']]);
        self::assertSame(['order_number'], array_column($connection->fetchAllAssociative("PRAGMA index_info('tilttek_loot_draw_order')"), 'name'));
    }

    #[Override]
    protected function repository(): LootDrawRepository
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        return new DoctrineLootDrawRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}
