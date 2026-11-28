<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\CollectionStatus;
use Tilttek\GameCatalog\Import\CollectionFile;
use Tilttek\GameCatalog\Import\CollectionImport;
use Tilttek\GameCatalog\Import\CollectionItem;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Event\CopyAdded;
use Tilttek\GameShelf\Event\CopyOfferedForTrade;
use Tilttek\GameShelf\Event\GameWished;
use Tilttek\GameShelf\GameShelves;
use Tilttek\GameShelf\Note;
use Tilttek\GameShelf\Shelf;
use Tilttek\GameShelf\ShelfImport;
use Tilttek\GameShelf\Store\InMemoryShelfStore;
use Tilttek\GameShelf\Tests\Unit\Fixture\Observed;
use Tilttek\GameShelf\Visibility;
use Tilttek\GameShelf\Wish;
use Tilttek\GameShelf\WishKind;
use Tilttek\GameShelf\WishLevel;

#[CoversClass(GameShelves::class)]
#[CoversClass(ShelfImport::class)]
final class ImportTest extends TestCase
{
    private InMemoryShelfStore $store;

    private Observed $observed;

    private GameShelves $shelves;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryShelfStore();
        $this->observed = new Observed();
        $this->shelves = new GameShelves($this->store, new Catalog(new InMemoryCatalogStore(), new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC'))), $this->observed);
    }

    /**
     * @param list<CollectionItem> $items
     */
    private function import(array $items, string $member = 'member'): ShelfImport
    {
        return $this->shelves->import($member, new CollectionImport($items, [], [], 0, new CollectionFile([])));
    }

    /**
     * @return array{int, int, int, int, int, int, list<string>}
     */
    private function counts(ShelfImport $import): array
    {
        return [$import->copiesAdded, $import->copiesUpdated, $import->wishesAdded, $import->wishesUpdated, $import->playsUpdated, $import->unchanged, $import->refused];
    }

    public function testAFileFillsAnEmptyShelf(): void
    {
        $import = $this->import([
            new CollectionItem('harbour', [CollectionStatus::Owned, CollectionStatus::ForTrade], null, 12),
            new CollectionItem('tide', [CollectionStatus::WantToBuy, CollectionStatus::Wishlist], 2, null),
            new CollectionItem('ebb', [CollectionStatus::Preordered, CollectionStatus::WantToPlay], 9, 0),
            new CollectionItem('nothing', [], null, null),
        ]);

        self::assertSame([2, 0, 2, 0, 1, 1, []], $this->counts($import));
        self::assertSame(1, $import->shelf->version);
        self::assertSame(1, $this->store->shelf('member')->version);
        $shelf = $import->shelf;
        self::assertSame([CopyStatus::Owned, CopyStatus::ForTrade], $shelf->copy('harbour')?->statuses);
        self::assertSame('harbour', $shelf->copy('harbour')->game);
        self::assertSame([CopyStatus::Preordered], $shelf->copy('ebb')?->statuses);
        self::assertSame([WishKind::Wishlist, WishKind::WantToBuy], $shelf->wish('tide')?->kinds);
        self::assertSame(WishLevel::LoveToHave, $shelf->wish('tide')->level);
        self::assertSame([WishKind::WantToPlay], $shelf->wish('ebb')?->kinds);
        self::assertNull($shelf->wish('ebb')->level);
        self::assertSame(12, $shelf->note('harbour')?->plays);
        self::assertNull($shelf->note('ebb'));
        self::assertNull($shelf->copy('nothing'));
        self::assertEquals([
            new CopyAdded('member', 'harbour', 'harbour'),
            new CopyOfferedForTrade('member', 'harbour', 'harbour'),
            new CopyAdded('member', 'ebb', 'ebb'),
            new GameWished('member', 'tide'),
            new GameWished('member', 'ebb'),
        ], $this->observed->take());
    }

    public function testAnImportAgainChangesOnlyWhatTheFileChanged(): void
    {
        $this->store->save(new Shelf(
            'member',
            [new Copy('harbour', 'harbour', [CopyStatus::Owned], 'French', 'Attic', false), new Copy('mine', 'harbour')],
            [new Wish('lanterns'), new Wish('tide', [WishKind::Wishlist], WishLevel::MustHave)],
            [new Note('harbour', 8, 'Great', ['family'], 3)],
            Visibility::Members,
            Visibility::Public,
        ));

        $import = $this->import([
            new CollectionItem('harbour', [CollectionStatus::Owned, CollectionStatus::ForTrade], null, 4),
            new CollectionItem('tide', [CollectionStatus::Wishlist], null, null),
            new CollectionItem('lanterns', [CollectionStatus::Wishlist], 3, 0),
            new CollectionItem('ebb', [CollectionStatus::Wishlist], null, null),
        ]);

        self::assertSame([0, 1, 1, 1, 1, 1, []], $this->counts($import));
        $shelf = $import->shelf;
        self::assertSame([Visibility::Members, Visibility::Public, 2], [$shelf->shelfVisibility, $shelf->wishlistVisibility, $shelf->version]);
        $copy = $shelf->copy('harbour');
        self::assertSame([[CopyStatus::Owned, CopyStatus::ForTrade], 'French', 'Attic', false], [$copy?->statuses, $copy?->edition, $copy?->location, $copy?->bringable]);
        self::assertSame([CopyStatus::Owned], $shelf->copy('mine')?->statuses);
        self::assertSame(WishLevel::MustHave, $shelf->wish('tide')?->level);
        self::assertSame(WishLevel::LikeToHave, $shelf->wish('lanterns')?->level);
        self::assertNull($shelf->wish('ebb')?->level);
        self::assertSame(['lanterns', 'tide', 'ebb'], array_map(static fn (Wish $wish): string => $wish->game, $shelf->wishes()));
        $note = $shelf->note('harbour');
        self::assertSame([8, 'Great', ['family'], 4], [$note?->rating, $note?->comment, $note?->tags, $note?->plays]);
        self::assertEquals([new CopyOfferedForTrade('member', 'harbour', 'harbour'), new GameWished('member', 'ebb')], $this->observed->take());
    }

    public function testAnImportOfTheSameFileChangesNothing(): void
    {
        $items = [
            new CollectionItem('harbour', [CollectionStatus::Owned], null, 4),
            new CollectionItem('tide', [CollectionStatus::Wishlist], 1, null),
        ];
        $this->import($items);
        $this->observed->take();

        self::assertSame([0, 0, 0, 0, 0, 2, []], $this->counts($this->import($items)));
        self::assertSame([], $this->observed->take());
    }

    public function testAnImportOfStatusesThatAreNotCopiesOrWishesChangesNothingTheSecondTime(): void
    {
        $items = [
            new CollectionItem('harbour', [CollectionStatus::Wishlist, CollectionStatus::Owned], null, null),
            new CollectionItem('tide', [CollectionStatus::Owned, CollectionStatus::Wishlist], null, null),
        ];

        self::assertSame([2, 0, 2, 0, 0, 0, []], $this->counts($this->import($items)));
        self::assertSame([0, 0, 0, 0, 0, 2, []], $this->counts($this->import($items)));
    }

    public function testAFileLevelReplacesTheLevelOfAWishKeptBefore(): void
    {
        $this->import([new CollectionItem('tide', [CollectionStatus::Wishlist], 1, null)]);

        $import = $this->import([new CollectionItem('tide', [CollectionStatus::Wishlist], 3, null)]);

        self::assertSame([0, 0, 0, 1, 0, 0, []], $this->counts($import));
        self::assertSame(WishLevel::LikeToHave, $import->shelf->wish('tide')?->level);
    }

    public function testAnImportCountsNothingByDefault(): void
    {
        $import = new ShelfImport(new Shelf('member'));

        self::assertSame([0, 0, 0, 0, 0, 0, []], $this->counts($import));
        self::assertSame(0, $import->copiesPreviouslyOwned);
    }

    public function testAWishWhoseKindsChangeIsUpdated(): void
    {
        $this->import([new CollectionItem('tide', [CollectionStatus::Wishlist], 1, null)]);

        $import = $this->import([new CollectionItem('tide', [CollectionStatus::WantToPlay], null, null)]);

        self::assertSame([0, 0, 0, 1, 0, 0, []], $this->counts($import));
        self::assertSame([[WishKind::WantToPlay], WishLevel::MustHave], [$import->shelf->wish('tide')?->kinds, $import->shelf->wish('tide')?->level]);
    }

    public function testPlaysBackToZeroDropAnEmptyNote(): void
    {
        $this->import([new CollectionItem('harbour', [], null, 4)]);

        $import = $this->import([new CollectionItem('harbour', [], null, 0)]);

        self::assertSame([0, 0, 0, 0, 1, 0, []], $this->counts($import));
        self::assertNull($import->shelf->note('harbour'));
    }

    public function testStatusesThatDoNotGoTogetherAreRefused(): void
    {
        $import = $this->import([
            new CollectionItem('harbour', [CollectionStatus::Owned, CollectionStatus::PreviouslyOwned], null, 4),
            new CollectionItem('tide', [CollectionStatus::ForTrade], null, null),
            new CollectionItem('ebb', [CollectionStatus::Owned], null, null),
        ]);

        self::assertSame([1, 0, 0, 0, 0, 0, ['harbour', 'tide']], $this->counts($import));
        self::assertNull($import->shelf->note('harbour'));
    }

    public function testAFullShelfRefusesTheNewGamesOfTheFile(): void
    {
        $this->store->save(new Shelf(
            'member',
            array_map(static fn (int $i): Copy => new Copy('c'.$i, 'game'), range(1, Shelf::MAX_COPIES)),
            array_map(static fn (int $i): Wish => new Wish('w'.$i), range(1, Shelf::MAX_WISHES)),
            array_map(static fn (int $i): Note => new Note('n'.$i, plays: 1), range(1, Shelf::MAX_NOTES)),
        ));

        $import = $this->import([
            new CollectionItem('copy', [CollectionStatus::Owned], null, null),
            new CollectionItem('wish', [CollectionStatus::Wishlist], null, null),
            new CollectionItem('note', [], null, 2),
            new CollectionItem('c1', [CollectionStatus::Owned, CollectionStatus::ForTrade], null, null),
            new CollectionItem('w1', [CollectionStatus::Wishlist], 1, null),
            new CollectionItem('n1', [], null, 5),
            new CollectionItem('none', [], null, 0),
        ]);

        self::assertSame([0, 1, 0, 1, 1, 1, ['copy', 'wish', 'note']], $this->counts($import));
    }

    public function testAShelfWithRoomTakesTheLastGames(): void
    {
        $this->store->save(new Shelf(
            'member',
            array_map(static fn (int $i): Copy => new Copy('c'.$i, 'game'), range(2, Shelf::MAX_COPIES)),
            array_map(static fn (int $i): Wish => new Wish('w'.$i), range(2, Shelf::MAX_WISHES)),
            array_map(static fn (int $i): Note => new Note('n'.$i, plays: 1), range(2, Shelf::MAX_NOTES)),
        ));

        $import = $this->import([
            new CollectionItem('copy', [CollectionStatus::Owned], null, null),
            new CollectionItem('wish', [CollectionStatus::Wishlist], null, null),
            new CollectionItem('note', [], null, 2),
        ]);

        self::assertSame([1, 0, 1, 0, 1, 0, []], $this->counts($import));
    }
}
