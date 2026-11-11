<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\CollectionsSchemaListener;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\CollectionsTables;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\Rows;
use UnexpectedValueException;

#[CoversClass(CollectionsTables::class)]
#[CoversClass(CollectionsSchemaListener::class)]
#[CoversClass(Rows::class)]
final class TablesTest extends TestCase
{
    public function testTheTables(): void
    {
        $schema = CollectionsTables::configure(new Schema());

        $copies = $schema->getTable(CollectionsTables::COPIES);
        self::assertSame(['id', 'item', 'owner', 'source', 'copy_key', 'acquired_at', 'tradable', 'moved'], $this->columns($copies));
        self::assertSame([32, 64, 320, 16, 300], $this->lengths($copies, 'id', 'item', 'owner', 'source', 'copy_key'));
        self::assertSame([Types::DATETIME_IMMUTABLE, Types::BOOLEAN, Types::BOOLEAN], $this->types($copies, 'acquired_at', 'tradable', 'moved'));
        self::assertSame(['id'], $this->primary($copies));
        self::assertSame(['tilttek_collections_copy_owner', 'tilttek_collections_copy_source', 'primary'], $this->indexes($copies));

        $completions = $schema->getTable(CollectionsTables::COMPLETIONS);
        self::assertSame(['set_code', 'member', 'completed_at'], $this->columns($completions));
        self::assertSame([64, 320], $this->lengths($completions, 'set_code', 'member'));
        self::assertSame(['set_code', 'member'], $this->primary($completions));
        self::assertSame(['tilttek_collections_completion_member', 'primary'], $this->indexes($completions));

        $openings = $schema->getTable(CollectionsTables::OPENINGS);
        self::assertSame(['opening_key', 'member', 'pack', 'seed', 'commitment', 'client_seed', 'items', 'copies', 'opened_at'], $this->columns($openings));
        self::assertSame([200, 320, 64, 128, 64], $this->lengths($openings, 'opening_key', 'member', 'pack', 'seed', 'commitment'));
        self::assertSame([Types::TEXT, Types::TEXT, Types::DATETIME_IMMUTABLE], $this->types($openings, 'items', 'copies', 'opened_at'));
        self::assertSame(['opening_key'], $this->primary($openings));
        self::assertSame(['tilttek_collections_opening_member', 'primary'], $this->indexes($openings));

        $choices = $schema->getTable(CollectionsTables::CHOICES);
        self::assertSame(['member', 'sides', 'copy_id'], $this->columns($choices));
        self::assertSame([320, 32], $this->lengths($choices, 'member', 'copy_id'));
        self::assertSame([Types::INTEGER], $this->types($choices, 'sides'));
        self::assertSame(['member', 'sides'], $this->primary($choices));

        $daily = $schema->getTable(CollectionsTables::DAILY);
        self::assertSame(['day', 'item', 'origin'], $this->columns($daily));
        self::assertSame([10, 64, 16], $this->lengths($daily, 'day', 'item', 'origin'));
        self::assertSame(['day'], $this->primary($daily));

        $definitions = $schema->getTable(CollectionsTables::DEFINITIONS);
        self::assertSame(['kind', 'code', 'position', 'definition'], $this->columns($definitions));
        self::assertSame([16, 64], $this->lengths($definitions, 'kind', 'code'));
        self::assertSame([Types::INTEGER, Types::TEXT], $this->types($definitions, 'position', 'definition'));
        self::assertSame(['kind', 'code'], $this->primary($definitions));
    }

    public function testTablesAlreadyThereAreLeftAsTheyAre(): void
    {
        $schema = CollectionsTables::configure(new Schema());

        self::assertCount(6, CollectionsTables::configure($schema)->getTables());
    }

    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $collections = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new CollectionsSchemaListener($collections);

        $event = new GenerateSchemaEventArgs($this->entityManager($collections), new Schema());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(CollectionsTables::COPIES));

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($other), new Schema());
        $listener->postGenerateSchema($elsewhere);
        self::assertSame([], $elsewhere->getSchema()->getTables());
    }

    public function testTheInsertionThatIgnoresARowAlreadyThere(): void
    {
        self::assertSame('INSERT INTO t (a) VALUES (?) ON CONFLICT (a) DO NOTHING', Rows::ignoring(new PostgreSQLPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new MySQLPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new MariaDBPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT OR IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new SQLitePlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT INTO t (a) VALUES (?)', Rows::ignoring(new OraclePlatform(), 'INTO t (a) VALUES (?)', 'a'));
    }

    public function testTheValuesOfARow(): void
    {
        self::assertSame(42, Rows::integer(42));
        self::assertSame(42, Rows::integer('42'));
        self::assertSame(-7, Rows::integer('-7'));
        self::assertSame('a', Rows::text('a'));
        self::assertTrue(Rows::boolean(true));
        self::assertFalse(Rows::boolean(false));
        self::assertTrue(Rows::boolean(1));
        self::assertTrue(Rows::boolean('1'));
        self::assertFalse(Rows::boolean(0));
        self::assertFalse(Rows::boolean('0'));
        self::assertSame('["a","b"]', Rows::encode(['a', 'b']));
        self::assertSame(['a', 'b'], Rows::texts('["a","b"]'));
        self::assertSame([], Rows::texts('[]'));
        self::assertSame('2026-11-07T15:00:00+00:00', Rows::utc(new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto'))->format(\DATE_ATOM));
        self::assertSame('2026-11-07T15:00:00+00:00', Rows::date('2026-11-07 15:00:00')->format(\DATE_ATOM));
        self::assertEquals(ResourceReference::of('member', '1'), Rows::reference('member:1'));
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function wrongValues(): iterable
    {
        yield 'an integer with a space' => [static fn (): int => Rows::integer('4 2'), 'An integer column holds string.'];
        yield 'an integer with a new line' => [static fn (): int => Rows::integer("42\n"), 'An integer column holds string.'];
        yield 'a float' => [static fn (): int => Rows::integer(4.2), 'An integer column holds float.'];
        yield 'an object' => [static fn (): string => Rows::text(new stdClass()), 'A text column holds stdClass.'];
        yield 'a boolean of text' => [static fn (): bool => Rows::boolean('yes'), 'An integer column holds string.'];
        yield 'no JSON' => [static fn (): array => Rows::texts('[a'), 'A list column holds no JSON: Syntax error'];
        yield 'JSON too deep' => [static fn (): array => Rows::texts('[[[["a"]]]]'), 'A list column holds no JSON: Maximum stack depth exceeded'];
        yield 'a map' => [static fn (): array => Rows::texts('{"a":"b"}'), 'A list column holds no list.'];
        yield 'a number' => [static fn (): array => Rows::texts('3'), 'A list column holds no list.'];
        yield 'a list of numbers' => [static fn (): array => Rows::texts('[3]'), 'A text column holds int.'];
    }

    /**
     * @param callable(): mixed $read
     */
    #[DataProvider('wrongValues')]
    public function testAWrongValueIsRefused(callable $read, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $read();
    }

    public function testThreeLevelsOfJsonAreRead(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException('A text column holds array.'));

        Rows::texts('[[["a"]]]');
    }

    /**
     * @return list<?int>
     */
    private function lengths(Table $table, string ...$columns): array
    {
        return array_map(static fn (string $column): ?int => $table->getColumn($column)->getLength(), array_values($columns));
    }

    /**
     * @return list<string>
     */
    private function types(Table $table, string ...$columns): array
    {
        return array_map(static fn (string $column): string => Type::lookupName($table->getColumn($column)->getType()), array_values($columns));
    }

    /**
     * @return list<string>
     */
    private function columns(Table $table): array
    {
        return array_map(static fn (Column $column): string => $column->getName(), $table->getColumns());
    }

    /**
     * @return list<string>
     */
    private function indexes(Table $table): array
    {
        return array_values(array_map(static fn (Index $index): string => $index->getObjectName()->toString(), $table->getIndexes()));
    }

    /**
     * @return list<string>
     */
    private function primary(Table $table): array
    {
        $key = $table->getPrimaryKeyConstraint();
        self::assertNotNull($key);

        return array_map(static fn (UnqualifiedName $name): string => $name->toString(), $key->getColumnNames());
    }

    private function entityManager(Connection $connection): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([], true);
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($connection, $configuration);
    }
}
