<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\GameShelf\BringableGame;
use Tilttek\GameShelf\Contract\ShelfObserver;
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Event\CopyAdded;
use Tilttek\GameShelf\Event\CopyOfferedForTrade;
use Tilttek\GameShelf\Event\CopyRemoved;
use Tilttek\GameShelf\Event\CopyWithdrawnFromTrade;
use Tilttek\GameShelf\Event\GameWished;
use Tilttek\GameShelf\Event\ShelfEvent;
use Tilttek\GameShelf\Event\WishWithdrawn;
use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Exception\ShelfConflict;
use Tilttek\GameShelf\Exception\ShelfRefused;
use Tilttek\GameShelf\GameShelves;
use Tilttek\GameShelf\Note;
use Tilttek\GameShelf\Shelf;
use Tilttek\GameShelf\Store\InMemoryShelfStore;
use Tilttek\GameShelf\Tests\Unit\Fixture\Observed;
use Tilttek\GameShelf\Viewer;
use Tilttek\GameShelf\Visibility;
use Tilttek\GameShelf\Wish;
use Tilttek\GameShelf\WishLevel;

#[CoversClass(GameShelves::class)]
#[CoversClass(CopyAdded::class)]
#[CoversClass(CopyRemoved::class)]
#[CoversClass(CopyOfferedForTrade::class)]
#[CoversClass(CopyWithdrawnFromTrade::class)]
#[CoversClass(GameWished::class)]
#[CoversClass(WishWithdrawn::class)]
final class GameShelvesTest extends TestCase
{
    private Catalog $catalog;

    private InMemoryShelfStore $store;

    private Observed $observed;

    private GameShelves $shelves;

    #[Override]
    protected function setUp(): void
    {
        $this->catalog = new Catalog(new InMemoryCatalogStore(), new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC')));

        foreach (['harbour', 'lanterns', 'tide', 'ebb'] as $game) {
            $this->catalog->add($game, new Details(ucfirst($game)));
        }

        $this->catalog->hide($this->catalog->add('hidden', new Details('Hidden'))->id);
        $this->store = new InMemoryShelfStore();
        $this->observed = new Observed();
        $this->shelves = new GameShelves($this->store, $this->catalog, $this->observed);
    }

    public function testAnUnknownMemberHasAnEmptyShelf(): void
    {
        $shelf = $this->shelves->shelf('member');

        self::assertSame([0, []], [$shelf->version, $shelf->copies()]);
    }

    public function testTheMemberIsAnIdentifier(): void
    {
        $this->expectException(InvalidShelf::class);

        $this->shelves->shelf('someone@example.org');
    }

    public function testACopyIsPlacedAndTold(): void
    {
        $shelf = $this->shelves->place('member', new Copy('c1', 'harbour'));

        self::assertSame(1, $shelf->version);
        self::assertSame('harbour', $this->shelves->shelf('member')->copy('c1')?->game);
        self::assertEquals([new CopyAdded('member', 'c1', 'harbour')], $this->observed->take());
        self::assertSame('c1', new CopyAdded('member', 'c1', 'harbour')->copy);
    }

    public function testACopyOfferedForTradeIsTold(): void
    {
        $this->shelves->place('member', new Copy('c1', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade]));
        self::assertEquals([new CopyAdded('member', 'c1', 'harbour'), new CopyOfferedForTrade('member', 'c1', 'harbour')], $this->observed->take());

        $this->shelves->place('member', new Copy('c1', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade], 'French'));
        self::assertSame([], $this->observed->take());

        $this->shelves->place('member', new Copy('c1', 'harbour'));
        self::assertEquals([new CopyWithdrawnFromTrade('member', 'c1', 'harbour')], $this->observed->take());

        $this->shelves->place('member', new Copy('c1', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade]));
        self::assertEquals([new CopyOfferedForTrade('member', 'c1', 'harbour')], $this->observed->take());

        $this->shelves->remove('member', 'c1');
        self::assertEquals([new CopyWithdrawnFromTrade('member', 'c1', 'harbour'), new CopyRemoved('member', 'c1', 'harbour')], $this->observed->take());
        self::assertSame([], $this->shelves->shelf('member')->copies());
    }

    public function testACopyGivenAnotherGameIsRemovedThenAdded(): void
    {
        $this->shelves->place('member', new Copy('c1', 'harbour'));
        $this->observed->take();

        $this->shelves->place('member', new Copy('c1', 'tide'));

        self::assertEquals([new CopyRemoved('member', 'c1', 'harbour'), new CopyAdded('member', 'c1', 'tide')], $this->observed->take());
    }

    public function testACopyOfAGameMissingFromTheCatalogueIsRefused(): void
    {
        $this->expectException(EntryNotFound::class);

        $this->shelves->place('member', new Copy('c1', 'missing'));
    }

    public function testACopyOfAHiddenGameIsRefused(): void
    {
        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('The game hidden is hidden in the catalogue.');

        $this->shelves->place('member', new Copy('c1', 'hidden'));
    }

    public function testACopyKeptOfAGameHiddenSinceStillChanges(): void
    {
        $this->shelves->place('member', new Copy('c1', 'harbour'));
        $this->catalog->hide('harbour');

        $shelf = $this->shelves->place('member', new Copy('c1', 'harbour', [CopyStatus::PreviouslyOwned]));

        self::assertSame([CopyStatus::PreviouslyOwned], $shelf->copy('c1')?->statuses);
    }

    public function testAMissingCopyIsNotRemoved(): void
    {
        $this->expectException(ShelfRefused::class);

        $this->shelves->remove('member', 'c1');
    }

    public function testAGameIsWishedMovedAndWithdrawn(): void
    {
        $this->shelves->wish('member', new Wish('harbour'));
        $this->shelves->wish('member', new Wish('tide'));
        $this->shelves->wish('member', new Wish('ebb'));
        self::assertEquals([new GameWished('member', 'harbour'), new GameWished('member', 'tide'), new GameWished('member', 'ebb')], $this->observed->take());

        $this->shelves->moveWishBefore('member', 'ebb', 'harbour');
        $this->shelves->moveWishAfter('member', 'harbour', 'tide');
        self::assertSame(['ebb', 'tide', 'harbour'], array_map(static fn (Wish $wish): string => $wish->game, $this->shelves->shelf('member')->wishes()));

        $this->shelves->wish('member', new Wish('tide', level: WishLevel::MustHave));
        self::assertSame([], $this->observed->take());
        self::assertSame(WishLevel::MustHave, $this->shelves->shelf('member')->wish('tide')?->level);

        $shelf = $this->shelves->unwish('member', 'tide');
        self::assertEquals([new WishWithdrawn('member', 'tide')], $this->observed->take());
        self::assertSame(7, $shelf->version);
    }

    public function testAHiddenGameIsNotWished(): void
    {
        $this->expectException(ShelfRefused::class);

        $this->shelves->wish('member', new Wish('hidden'));
    }

    public function testAWishKeptOfAGameHiddenSinceStillChanges(): void
    {
        $this->shelves->wish('member', new Wish('harbour'));
        $this->catalog->hide('harbour');

        self::assertSame(WishLevel::DontBuy, $this->shelves->wish('member', new Wish('harbour', level: WishLevel::DontBuy))->wish('harbour')?->level);
    }

    public function testAGameMissingFromTheCatalogueIsNotWished(): void
    {
        $this->expectException(EntryNotFound::class);

        $this->shelves->wish('member', new Wish('missing'));
    }

    public function testANoteIsKeptForAGameOfTheCatalogue(): void
    {
        $this->catalog->hide('harbour');

        self::assertSame(9, $this->shelves->note('member', new Note('harbour', 9))->note('harbour')?->rating);
        self::assertNull($this->shelves->note('member', new Note('harbour'))->note('harbour'));
        self::assertSame([], $this->observed->take());
    }

    public function testAnEmptyNoteOfAGameNoLongerInTheCatalogueIsDropped(): void
    {
        $shelf = $this->store->save(new Shelf('member', notes: [new Note('gone', 3)]));

        self::assertSame(1, $shelf->version);
        self::assertSame([], $this->shelves->note('member', new Note('gone'))->notes());
    }

    public function testANoteOfAGameMissingFromTheCatalogueIsRefused(): void
    {
        $this->expectException(EntryNotFound::class);

        $this->shelves->note('member', new Note('missing', 3));
    }

    public function testTheMemberChoosesWhoSeesTheirShelf(): void
    {
        $this->shelves->place('member', new Copy('c1', 'harbour', location: 'Attic'));
        $this->shelves->wish('member', new Wish('tide'));

        self::assertSame([], $this->shelves->view('member', Viewer::Anyone)->copies);

        $shelf = $this->shelves->share('member', Visibility::Public, Visibility::Members);

        self::assertSame([Visibility::Public, Visibility::Members], [$shelf->shelfVisibility, $shelf->wishlistVisibility]);
        $view = $this->shelves->view('member', Viewer::Anyone);
        self::assertNull($view->copies[0]->location);
        self::assertSame([], $view->wishes);
        self::assertCount(1, $this->shelves->view('member', Viewer::Member)->wishes);
    }

    public function testAStaleShelfIsNotKept(): void
    {
        $this->shelves->place('member', new Copy('c1', 'harbour'));
        $stale = new Shelf('member', [new Copy('c2', 'tide')]);

        $this->expectException(ShelfConflict::class);
        $this->expectExceptionMessageIsOrContains('The shelf of the member member changed since it was read.');

        $this->store->save($stale);
    }

    public function testTheServiceWorksWithoutAnObserver(): void
    {
        $shelves = new GameShelves($this->store, $this->catalog);

        self::assertCount(1, $shelves->place('member', new Copy('c1', 'harbour'))->copies());
    }

    public function testAFactTheObserverFailsOnDoesNotStopTheOthers(): void
    {
        $observer = new class implements ShelfObserver {
            /** @var list<ShelfEvent> */
            public array $told = [];

            #[Override]
            public function notify(ShelfEvent $event): void
            {
                $this->told[] = $event;

                throw new RuntimeException(\sprintf('Failed on fact %d.', \count($this->told)));
            }
        };
        $shelves = new GameShelves($this->store, $this->catalog, $observer);

        try {
            $shelves->place('member', new Copy('c1', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade]));
            self::fail('The failure of the observer is raised.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Failed on fact 1.', $runtimeException->getMessage());
        }

        $kept = $this->store->shelf('member');

        self::assertEquals([new CopyAdded('member', 'c1', 'harbour'), new CopyOfferedForTrade('member', 'c1', 'harbour')], $observer->told);
        self::assertSame([1, ['c1']], [$kept->version, array_map(static fn (Copy $copy): string => $copy->id, $kept->copies())]);
    }

    public function testTheGamesTheMembersCanBringAreGivenOncePerMember(): void
    {
        $this->shelves->place('m1', new Copy('c1', 'harbour'));
        $this->shelves->place('m1', new Copy('c2', 'harbour', edition: 'English'));
        $this->shelves->place('m1', new Copy('c3', 'tide', bringable: false));
        $this->shelves->place('m1', new Copy('c4', 'ebb', [CopyStatus::Preordered]));
        $this->shelves->place('m1', new Copy('c5', 'lanterns'));
        $this->shelves->place('m2', new Copy('c1', 'harbour'));
        $this->shelves->place('m3', new Copy('c1', 'tide'));

        $games = static fn (iterable $given): array => array_map(
            static fn (BringableGame $game): string => $game->owner.':'.$game->game,
            [...$given],
        );

        self::assertSame(['m1:harbour', 'm1:lanterns', 'm2:harbour'], $games($this->shelves->bringable(['m1', 'm2', 'm1', 'nobody'], 100)));
        self::assertSame(['m2:harbour', 'm1:harbour'], $games($this->shelves->bringable(['m2', 'm1'], 2)));
        self::assertSame(['m1:harbour'], $games($this->shelves->bringable(['m1', 'm2'], 1)));
        self::assertSame([], $games($this->shelves->bringable([], 1)));
        self::assertCount(4, $games($this->shelves->bringable(['m1', 'm2', 'm3'], GameShelves::MAX_BRINGABLE)));
    }

    public function testTheGamesOfTooManyMembersAreNotRead(): void
    {
        $members = array_map(static fn (int $i): string => 'm'.$i, range(1, GameShelves::MAX_MEMBERS));
        self::assertSame([], [...$this->shelves->bringable($members, 1)]);

        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('The games of at most 2000 members are read at once.');

        $this->shelves->bringable([...$members, 'more'], 1);
    }

    public function testTheLimitOfTheGamesGivenIsBounded(): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('At most 1 to 10000 games are given at once.');

        $this->shelves->bringable(['m1'], 0);
    }

    public function testTheLimitOfTheGamesGivenHasAMost(): void
    {
        $this->expectException(InvalidShelf::class);

        $this->shelves->bringable(['m1'], GameShelves::MAX_BRINGABLE + 1);
    }

    public function testTheMembersWhoseGamesAreReadAreCheckedAtOnce(): void
    {
        $this->expectException(InvalidShelf::class);

        $this->shelves->bringable(['m1', 'someone@example.org'], 1);
    }
}
