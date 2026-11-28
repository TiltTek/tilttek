<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Import;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\CollectionStatus;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Entry;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\GameKind;
use Tilttek\GameCatalog\Import\CollectionFile;
use Tilttek\GameCatalog\Import\CollectionImport;
use Tilttek\GameCatalog\Import\CollectionImporter;
use Tilttek\GameCatalog\Import\CollectionItem;
use Tilttek\GameCatalog\Import\CollectionRow;
use Tilttek\GameCatalog\Import\RowError;
use Tilttek\GameCatalog\Origin;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;

#[CoversClass(CollectionImporter::class)]
#[CoversClass(CollectionImport::class)]
#[CoversClass(CollectionItem::class)]
final class CollectionImporterTest extends TestCase
{
    public function testTheRolePlayingGamesOfTheCatalogueAreImported(): void
    {
        $store = new InMemoryCatalogStore();
        $store->save(new Entry('rpg', new Details('Lanterns', kind: GameKind::RolePlaying), Origin::manual(), [ExternalId::rpgGeek(1), ExternalId::wikidata('Q1')]));
        $store->save(new Entry('board', new Details('Tide'), Origin::manual(), [ExternalId::boardGameGeek(2)]));
        $store->save(new Entry('hidden', new Details('Gone', kind: GameKind::RolePlaying), Origin::manual(), [ExternalId::rpgGeek(3)], hidden: true));
        $store->save(new Entry('other', new Details('Ebb', kind: GameKind::RolePlaying), Origin::manual(), [ExternalId::rpgGeek(5)]));

        $errors = [new RowError(9, 'Bad.')];
        $file = new CollectionFile([
            new CollectionRow(2, ExternalId::rpgGeek(1), [CollectionStatus::Owned], null, 4),
            new CollectionRow(3, ExternalId::boardGameGeek(2), [CollectionStatus::Owned], null, null),
            new CollectionRow(4, ExternalId::rpgGeek(3), [], null, null),
            new CollectionRow(5, ExternalId::rpgGeek(4), [], null, null),
            new CollectionRow(6, ExternalId::wikidata('Q1'), [CollectionStatus::Wishlist], 1, null),
            new CollectionRow(7, ExternalId::rpgGeek(5), [CollectionStatus::Wishlist], 2, 0),
        ], $errors, 3);

        $import = new CollectionImporter($store)->import($file);

        self::assertSame(['rpg', 'other'], array_map(static fn (CollectionItem $item): string => $item->entry, $import->items));
        self::assertSame([CollectionStatus::Owned], $import->items[0]->statuses);
        self::assertNull($import->items[0]->priority);
        self::assertSame(4, $import->items[0]->plays);
        self::assertSame(2, $import->items[1]->priority);
        self::assertSame(0, $import->items[1]->plays);
        self::assertSame(['rpggeek:3', 'rpggeek:4'], array_map(static fn (ExternalId $id): string => $id->key(), $import->unmatched));
        self::assertSame(['boardgamegeek:2'], array_map(static fn (ExternalId $id): string => $id->key(), $import->otherKinds));
        self::assertSame(1, $import->duplicates);
        self::assertSame($file, $import->file);
        self::assertSame($errors, $import->file->errors);
        self::assertSame(3, $import->file->moreErrors);

        $all = new CollectionImporter($store)->import($file, [GameKind::Board, GameKind::RolePlaying]);
        self::assertSame(['rpg', 'board', 'other'], array_map(static fn (CollectionItem $item): string => $item->entry, $all->items));
        self::assertSame([], $all->otherKinds);
    }
}
