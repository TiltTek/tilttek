<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Doctrine\DoctrineRecordedRollRepository;

/**
 * The journal on SQLite in memory, its table created from the mapping, each roll read back with the entity manager
 * cleared.
 */
#[CoversClass(DoctrineRecordedRollRepository::class)]
#[CoversClass(RecordedRoll::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheTable(): void
    {
        $this->repository();
        $schema = $this->entityManager?->getConnection()->createSchemaManager()->introspectSchema();

        self::assertNotNull($schema);
        $table = $schema->getTable(RecordedRoll::TABLE);
        self::assertSame(['id'], $table->getPrimaryKey()?->getColumns());
        self::assertSame(
            ['id' => [32, true], 'expression' => [null, true], 'seed' => [64, true], 'result' => [null, true], 'owner' => [320, false], 'rolled_at' => [null, true], 'rounding' => [32, true], 'context' => [320, false]],
            array_merge(...array_map(static fn (Column $column): array => [$column->getName() => [$column->getLength(), $column->getNotnull()]], $table->getColumns())),
        );
        $columns = static fn (string $index): array => array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(), $table->getIndex($index)->getIndexedColumns());
        self::assertSame(['owner'], $columns('tilttek_dice_roll_owner'));
        self::assertSame(['context', 'rolled_at'], $columns('tilttek_dice_roll_context'));
    }

    #[Override]
    protected function repository(): RecordedRollRepository
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        return new DoctrineRecordedRollRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}
