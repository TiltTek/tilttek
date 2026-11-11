<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\DefinitionKind;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\Item;
use Tilttek\Collections\Rarity;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\CollectionsTables;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\Rows;

#[CoversClass(DbalCatalog::class)]
final class DbalCatalogTest extends TestCase
{
    public function testDefinitionsAreKeptInTheOrderTheyWereFirstDefined(): void
    {
        $connection = Database::connection();
        $catalog = new DbalCatalog($connection);

        $catalog->define(DefinitionKind::Rarity, 'rare', 10);
        $catalog->define(DefinitionKind::Rarity, 'common', ['weight' => 80]);
        $catalog->define(DefinitionKind::Set, 'crew', ['name' => 'Crew']);
        $catalog->define(DefinitionKind::Item, 'pirate', ['name' => ['en' => 'Pirate', 'fr' => 'Pirate'], 'rarity' => 'rare']);
        $catalog->define(DefinitionKind::Item, 'deckhand', ['name' => 'Deckhand', 'rarity' => 'common', 'sets' => ['crew']]);
        $catalog->define(DefinitionKind::Rarity, 'rare', 20);

        self::assertSame(['rare' => 20, 'common' => 80], array_combine(array_map(static fn (Rarity $rarity): string => $rarity->code, $catalog->catalog()->rarities()), array_map(static fn (Rarity $rarity): int => $rarity->weight, $catalog->catalog()->rarities())));
        self::assertSame(['pirate', 'deckhand'], array_map(static fn (Item $item): string => $item->code, $catalog->catalog()->items()));
        self::assertSame([['rarity', 'common', 2], ['rarity', 'rare', 1]], array_map(static fn (array $row): array => [$row['kind'], $row['code'], Rows::integer($row['position'])], $connection->fetchAllAssociative(\sprintf("SELECT kind, code, position FROM %s WHERE kind = 'rarity' ORDER BY code", CollectionsTables::DEFINITIONS))));
        self::assertSame('{"name":{"en":"Pirate","fr":"Pirate"},"rarity":"rare"}', $connection->fetchOne(\sprintf("SELECT definition FROM %s WHERE code = 'pirate'", CollectionsTables::DEFINITIONS)));
    }

    public function testADefinitionThatBreaksTheCatalogIsNotWritten(): void
    {
        $catalog = new DbalCatalog(Database::connection());
        $catalog->define(DefinitionKind::Rarity, 'common', 1);

        foreach ([
            [DefinitionKind::Item, 'pirate', ['name' => 'Pirate', 'rarity' => 'rare'], 'No rarity is named "rare".'],
            [DefinitionKind::Item, 'deckhand', ['name' => 'Deckhand', 'rarity' => 'common', 'sets' => ['crew']], 'No set is named "crew".'],
            [DefinitionKind::Item, 'Pirate', ['name' => 'Pirate', 'rarity' => 'common'], '"Pirate" is not a item code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'],
        ] as [$kind, $code, $definition, $message]) {
            try {
                $catalog->define($kind, $code, $definition);
                self::fail($message);
            } catch (InvalidCollection|NotFound $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        self::assertSame([], $catalog->catalog()->items());
        self::assertSame([], $catalog->catalog()->sets());
    }

    public function testADefinitionIsRemovedUnlessTheCatalogNeedsIt(): void
    {
        $catalog = new DbalCatalog(Database::connection());
        $catalog->define(DefinitionKind::Rarity, 'common', 1);
        $catalog->define(DefinitionKind::Rarity, 'rare', 1);
        $catalog->define(DefinitionKind::Item, 'pirate', ['name' => 'Pirate', 'rarity' => 'rare']);

        self::assertFalse($catalog->remove(DefinitionKind::Pack, 'booster'));
        self::assertFalse($catalog->remove(DefinitionKind::Item, 'rare'));

        try {
            $catalog->remove(DefinitionKind::Rarity, 'rare');
            self::fail('Removed.');
        } catch (NotFound $notFound) {
            self::assertSame('No rarity is named "rare".', $notFound->getMessage());
        }

        self::assertTrue($catalog->remove(DefinitionKind::Item, 'pirate'));
        self::assertTrue($catalog->remove(DefinitionKind::Rarity, 'rare'));
        self::assertSame(['common'], array_map(static fn (Rarity $rarity): string => $rarity->code, $catalog->catalog()->rarities()));

        $catalog->define(DefinitionKind::Rarity, 'legendary', 1);
        self::assertSame(['common', 'legendary'], array_map(static fn (Rarity $rarity): string => $rarity->code, $catalog->catalog()->rarities()));
    }

    public function testTheSameCodeMayNameDefinitionsOfTwoKinds(): void
    {
        $catalog = new DbalCatalog(Database::connection());
        $catalog->define(DefinitionKind::Rarity, 'gold', 1);
        $catalog->define(DefinitionKind::Set, 'gold', ['name' => 'Gold']);
        $catalog->define(DefinitionKind::Item, 'gold', ['name' => 'Gold', 'rarity' => 'gold', 'sets' => ['gold']]);
        $catalog->define(DefinitionKind::Set, 'gold', ['name' => 'The gold set']);
        $catalog->define(DefinitionKind::Pack, 'gold', ['name' => 'Gold pack']);

        self::assertTrue($catalog->remove(DefinitionKind::Pack, 'gold'));
        self::assertSame(['gold'], array_map(static fn (Rarity $rarity): string => $rarity->code, $catalog->catalog()->rarities()));
        self::assertSame(['gold'], array_map(static fn (Item $item): string => $item->code, $catalog->catalog()->items()));
        self::assertSame('The gold set', $catalog->catalog()->set('gold')->name->in('en'));
    }

    public function testADefinitionIsReadSixtyFourLevelsDeep(): void
    {
        $connection = Database::connection();
        $connection->insert(CollectionsTables::DEFINITIONS, ['kind' => 'rarity', 'code' => 'common', 'position' => 1, 'definition' => str_repeat('[', 63).'1'.str_repeat(']', 63)]);

        $this->expectExceptionObject(InvalidCollection::because('Unknown key "0" in the rarity "common"; the keys are weight.'));
        new DbalCatalog($connection)->catalog();
    }

    public function testADefinitionDeeperThanSixtyFourLevelsIsNotRead(): void
    {
        $connection = Database::connection();
        $connection->insert(CollectionsTables::DEFINITIONS, ['kind' => 'rarity', 'code' => 'common', 'position' => 1, 'definition' => str_repeat('[', 64).'1'.str_repeat(']', 64)]);

        $this->expectExceptionObject(InvalidCollection::because('The definition of the rarity "common" is not JSON: Maximum stack depth exceeded'));
        new DbalCatalog($connection)->catalog();
    }

    public function testADefinitionThatIsNotJsonIsReported(): void
    {
        $connection = Database::connection();
        $connection->insert(CollectionsTables::DEFINITIONS, ['kind' => 'rarity', 'code' => 'common', 'position' => 1, 'definition' => '{']);

        $this->expectExceptionObject(InvalidCollection::because('The definition of the rarity "common" is not JSON: Syntax error'));
        new DbalCatalog($connection)->catalog();
    }

    public function testAnEmptyDatabaseHoldsAnEmptyCatalog(): void
    {
        self::assertSame([], new DbalCatalog(Database::connection())->catalog()->rarities());
    }
}
