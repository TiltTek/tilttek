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
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Import\CollectionFile;
use Tilttek\GameCatalog\Import\CollectionImport;
use Tilttek\GameCatalog\Import\CollectionItem;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Event\CopyWithdrawnFromTrade;
use Tilttek\GameShelf\GameShelves;
use Tilttek\GameShelf\ImportMode;
use Tilttek\GameShelf\ShelfImport;
use Tilttek\GameShelf\Store\InMemoryShelfStore;
use Tilttek\GameShelf\Tests\Unit\Fixture\Observed;

#[CoversClass(GameShelves::class)]
#[CoversClass(ShelfImport::class)]
#[CoversClass(ImportMode::class)]
#[CoversClass(Copy::class)]
final class SynchronizeTest extends TestCase
{
    private InMemoryShelfStore $store;

    private Observed $observed;

    private GameShelves $shelves;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryShelfStore();
        $catalog = new Catalog(new InMemoryCatalogStore(), new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC')));
        $this->observed = new Observed();
        $this->shelves = new GameShelves($this->store, $catalog, $this->observed);

        foreach (['harbour', 'tide', 'ebb'] as $number => $game) {
            $catalog->add($game, new Details(ucfirst($game)), [ExternalId::boardGameGeek($number + 1)]);
        }
    }

    /**
     * @param list<CollectionItem> $items
     * @param list<ExternalId>     $unmatched
     * @param list<ExternalId>     $otherKinds
     */
    private function import(array $items, ImportMode $mode = ImportMode::Synchronize, array $unmatched = [], array $otherKinds = []): ShelfImport
    {
        return $this->shelves->import('member', new CollectionImport($items, $unmatched, $otherKinds, 0, new CollectionFile([])), $mode);
    }

    private function owned(string $game): CollectionItem
    {
        return new CollectionItem($game, [CollectionStatus::Owned], null, null);
    }

    public function testAnImportMarksTheCopiesItPlacesAsImported(): void
    {
        $shelf = $this->import([$this->owned('harbour')], ImportMode::Add)->shelf;

        self::assertTrue($shelf->copy('harbour')?->imported);
        self::assertSame(ImportMode::Add, ImportMode::from('add'));
        self::assertSame('synchronize', ImportMode::Synchronize->value);
    }

    public function testAnImportAddsOnlyByDefault(): void
    {
        $this->import([$this->owned('harbour'), $this->owned('tide')], ImportMode::Add);

        $import = $this->shelves->import('member', new CollectionImport([$this->owned('harbour')], [], [], 0, new CollectionFile([])));

        self::assertSame(0, $import->copiesPreviouslyOwned);
        self::assertSame([CopyStatus::Owned], $import->shelf->copy('tide')?->statuses);
    }

    public function testASynchronizedImportMovesTheImportedCopiesTheFileNoLongerNames(): void
    {
        $this->import([
            new CollectionItem('harbour', [CollectionStatus::Owned, CollectionStatus::ForTrade], null, 3),
            $this->owned('tide'),
            new CollectionItem('ebb', [CollectionStatus::Preordered], null, null),
        ]);
        $this->shelves->place('member', new Copy('tide', 'tide', [CopyStatus::Owned], 'French', 'Attic', false));
        $this->observed->take();

        $import = $this->import([$this->owned('tide')]);

        self::assertSame(2, $import->copiesPreviouslyOwned);
        self::assertSame([0, 0, 0, 0, 0, 1, []], [$import->copiesAdded, $import->copiesUpdated, $import->wishesAdded, $import->wishesUpdated, $import->playsUpdated, $import->unchanged, $import->refused]);
        $shelf = $this->store->shelf('member');
        self::assertSame($shelf->version, $import->shelf->version);
        self::assertSame([CopyStatus::PreviouslyOwned], $shelf->copy('harbour')?->statuses);
        self::assertTrue($shelf->copy('harbour')->imported);
        self::assertSame([CopyStatus::PreviouslyOwned], $shelf->copy('ebb')?->statuses);
        self::assertSame(3, $shelf->note('harbour')?->plays);
        $tide = $shelf->copy('tide');
        self::assertSame([[CopyStatus::Owned], 'French', 'Attic', false, true], [$tide?->statuses, $tide?->edition, $tide?->location, $tide?->bringable, $tide?->imported]);
        self::assertEquals([new CopyWithdrawnFromTrade('member', 'harbour', 'harbour')], $this->observed->take());
    }

    public function testASynchronizedImportNeverTouchesTheCopiesEnteredByHand(): void
    {
        $this->shelves->place('member', new Copy('mine', 'harbour'));
        $this->shelves->place('member', new Copy('tide', 'tide', [CopyStatus::Owned, CopyStatus::ForTrade]));

        $import = $this->import([$this->owned('ebb')]);

        self::assertSame([1, 0], [$import->copiesAdded, $import->copiesPreviouslyOwned]);
        self::assertSame([CopyStatus::Owned], $import->shelf->copy('mine')?->statuses);
        self::assertSame([CopyStatus::Owned, CopyStatus::ForTrade], $import->shelf->copy('tide')?->statuses);
        self::assertFalse($import->shelf->copy('tide')->imported);
    }

    public function testACopyAlreadyPreviouslyOwnedIsNotCountedAgain(): void
    {
        $this->import([new CollectionItem('harbour', [CollectionStatus::PreviouslyOwned], null, null), $this->owned('tide')]);
        $this->import([]);

        $import = $this->import([]);

        self::assertSame([0, 0], [$import->copiesPreviouslyOwned, $import->unchanged]);
        self::assertSame([CopyStatus::PreviouslyOwned], $import->shelf->copy('tide')?->statuses);
    }

    public function testAGameTheFileNamesOnlyAsAWishIsNoLongerACopy(): void
    {
        $this->import([$this->owned('harbour')]);

        $import = $this->import([new CollectionItem('harbour', [CollectionStatus::Wishlist], null, 5)]);

        self::assertSame([1, 1, 1], [$import->copiesPreviouslyOwned, $import->wishesAdded, $import->playsUpdated]);
        self::assertSame([CopyStatus::PreviouslyOwned], $import->shelf->copy('harbour')?->statuses);
    }

    public function testAGameWhoseStatusesAreRefusedKeepsItsCopy(): void
    {
        $this->import([$this->owned('harbour')]);

        $import = $this->import([new CollectionItem('harbour', [CollectionStatus::Owned, CollectionStatus::PreviouslyOwned], null, null)]);

        self::assertSame([0, ['harbour']], [$import->copiesPreviouslyOwned, $import->refused]);
        self::assertSame([CopyStatus::Owned], $import->shelf->copy('harbour')?->statuses);
    }

    public function testAGameTheImportLeftOutKeepsItsCopy(): void
    {
        $this->import([$this->owned('harbour'), $this->owned('tide'), $this->owned('ebb')]);

        $import = $this->import([], unmatched: [ExternalId::boardGameGeek(1), ExternalId::boardGameGeek(99)], otherKinds: [ExternalId::boardGameGeek(2)]);

        self::assertSame(1, $import->copiesPreviouslyOwned);
        self::assertSame([CopyStatus::Owned], $import->shelf->copy('harbour')?->statuses);
        self::assertSame([CopyStatus::Owned], $import->shelf->copy('tide')?->statuses);
        self::assertSame([CopyStatus::PreviouslyOwned], $import->shelf->copy('ebb')?->statuses);
    }

    public function testAnImportThatAddsOnlyKeepsTheCopiesTheFileNoLongerNames(): void
    {
        $this->import([$this->owned('harbour')], ImportMode::Add);

        $import = $this->import([], ImportMode::Add, [ExternalId::boardGameGeek(1)]);

        self::assertSame([0, [CopyStatus::Owned]], [$import->copiesPreviouslyOwned, $import->shelf->copy('harbour')?->statuses]);
    }

    public function testACopyPlacedOverAnImportedCopyOfTheSameGameStaysImported(): void
    {
        $this->import([$this->owned('harbour')]);

        $kept = $this->shelves->place('member', new Copy('harbour', 'harbour', edition: 'Deluxe'));

        self::assertTrue($kept->copy('harbour')?->imported);
        self::assertSame('Deluxe', $kept->copy('harbour')->edition);
    }

    public function testACopyPlacedOverAnImportedCopyOfAnotherGameIsEnteredByHand(): void
    {
        $this->import([$this->owned('harbour')]);

        $kept = $this->shelves->place('member', new Copy('harbour', 'tide'));

        self::assertFalse($kept->copy('harbour')?->imported);
    }

    public function testACopyPlacedOverACopyEnteredByHandStaysEnteredByHand(): void
    {
        $this->shelves->place('member', new Copy('harbour', 'harbour'));

        self::assertFalse($this->shelves->place('member', new Copy('harbour', 'harbour', edition: 'Deluxe'))->copy('harbour')?->imported);
        self::assertTrue($this->shelves->place('member', new Copy('other', 'harbour', imported: true))->copy('other')?->imported);
    }

    public function testTheMarkOfAnImportFollowsTheCopy(): void
    {
        $copy = new Copy('harbour', 'harbour', [CopyStatus::Owned], 'French', 'Attic', false, true);

        self::assertFalse(new Copy('harbour', 'harbour')->imported);
        self::assertTrue($copy->withStatuses([CopyStatus::Preordered])->imported);
        self::assertTrue($copy->withoutPrivateDetails()->imported);
        $hand = $copy->withImported(false);
        self::assertSame([false, [CopyStatus::Owned], 'French', 'Attic', false], [$hand->imported, $hand->statuses, $hand->edition, $hand->location, $hand->bringable]);
        self::assertTrue($hand->withImported(true)->imported);
    }
}
