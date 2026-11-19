<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\DiceTower\Domain\Contract\TowerDropRepository;
use Tilttek\DiceTower\Domain\Model\TowerDrop;
use Tilttek\DiceTower\Infrastructure\Doctrine\DoctrineTowerDropRepository;

/**
 * The log on SQLite in memory, its table created from the mapping, each drop read back with the entity manager
 * cleared.
 */
#[CoversClass(DoctrineTowerDropRepository::class)]
#[CoversClass(TowerDrop::class)]
final class DoctrineTowerDropRepositoryTest extends TowerDropRepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheTable(): void
    {
        $this->repository();
        $schema = $this->entityManager?->getConnection()->createSchemaManager()->introspectTableByUnquotedName(TowerDrop::TABLE);

        self::assertNotNull($schema);
        self::assertSame(['"tower_table"', '"roll"', '"roller"', '"dropped_at"', '"position"'], array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $schema->getColumns()));
        self::assertTrue($schema->getColumn('position')->getAutoincrement());
        self::assertFalse($schema->getColumn('roller')->getNotnull());
        self::assertSame(320, $schema->getColumn('tower_table')->getLength());
        self::assertSame(320, $schema->getColumn('roller')->getLength());
        self::assertSame(32, $schema->getColumn('roll')->getLength());
    }

    #[Override]
    protected function repository(): TowerDropRepository
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        return new DoctrineTowerDropRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}
