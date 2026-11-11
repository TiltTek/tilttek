<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;
use Tilttek\DiceBundle\Infrastructure\Doctrine\DoctrineDiceMacroRepository;

/**
 * The macros on SQLite in memory, their table created from the mapping, each macro read back with the entity manager
 * cleared.
 */
#[CoversClass(DoctrineDiceMacroRepository::class)]
#[CoversClass(DiceMacro::class)]
final class DoctrineMacroRepositoryTest extends MacroRepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheTable(): void
    {
        $this->repository();
        $schema = $this->entityManager?->getConnection()->createSchemaManager()->introspectSchema();

        self::assertNotNull($schema);
        $table = $schema->getTable(DiceMacro::TABLE);
        self::assertSame(['level', 'owner', 'name'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(
            ['level' => [16, true], 'owner' => [320, true], 'name' => [32, true], 'expression' => [null, true]],
            array_map(static fn (Column $column): array => [$column->getLength(), $column->getNotnull()], ['level' => $table->getColumn('level'), 'owner' => $table->getColumn('owner'), 'name' => $table->getColumn('name'), 'expression' => $table->getColumn('expression')]),
        );
        self::assertCount(4, $table->getColumns());
        self::assertSame(['owner'], array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $table->getIndex('tilttek_dice_macro_owner')->getIndexedColumns()));
    }

    #[Override]
    protected function repository(): DiceMacroRepository
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        return new DoctrineDiceMacroRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}
