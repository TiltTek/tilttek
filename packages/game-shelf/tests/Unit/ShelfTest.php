<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Exception\ShelfRefused;
use Tilttek\GameShelf\Note;
use Tilttek\GameShelf\Shelf;
use Tilttek\GameShelf\ShelfView;
use Tilttek\GameShelf\Viewer;
use Tilttek\GameShelf\Visibility;
use Tilttek\GameShelf\Wish;
use Tilttek\GameShelf\WishLevel;

#[CoversClass(Shelf::class)]
#[CoversClass(ShelfView::class)]
#[CoversClass(ShelfRefused::class)]
final class ShelfTest extends TestCase
{
    /**
     * @param list<Wish> $wishes
     *
     * @return list<string>
     */
    private function games(array $wishes): array
    {
        return array_map(static fn (Wish $wish): string => $wish->game, $wishes);
    }

    private function wishes(string ...$games): Shelf
    {
        $shelf = new Shelf('member');

        foreach ($games as $game) {
            $shelf = $shelf->withWish(new Wish($game));
        }

        return $shelf;
    }

    public function testAShelfStartsEmptyAndPrivate(): void
    {
        $shelf = new Shelf('member');

        self::assertSame('member', $shelf->member);
        self::assertSame([], $shelf->copies());
        self::assertSame([], $shelf->wishes());
        self::assertSame([], $shelf->wishlist());
        self::assertSame([], $shelf->notes());
        self::assertSame(Visibility::Private, $shelf->shelfVisibility);
        self::assertSame(Visibility::Private, $shelf->wishlistVisibility);
        self::assertSame(0, $shelf->version);
    }

    public function testAShelfRefusesAMemberOutOfItsShape(): void
    {
        $this->expectException(InvalidShelf::class);

        new Shelf('someone@example.org');
    }

    public function testAShelfRefusesANegativeVersion(): void
    {
        self::assertSame(0, new Shelf('member', version: 0)->version);
        $this->expectExceptionMessageIsOrContains('The version of a shelf is 0 or more.');

        new Shelf('member', version: -1);
    }

    public function testAShelfRefusesAnIdentifierTwice(): void
    {
        $this->expectExceptionMessageIsOrContains('The copies of a shelf hold copy twice.');

        new Shelf('member', [new Copy('copy', 'a'), new Copy('copy', 'b')]);
    }

    public function testAShelfRefusesAWishTwice(): void
    {
        $this->expectExceptionMessageIsOrContains('The wishes of a shelf hold game twice.');

        new Shelf('member', wishes: [new Wish('game'), new Wish('game')]);
    }

    public function testAShelfRefusesAnEmptyNote(): void
    {
        $this->expectExceptionMessageIsOrContains('A shelf keeps no empty note.');

        new Shelf('member', notes: [new Note('game', 3), new Note('other')]);
    }

    public function testAShelfRefusesTooManyCopies(): void
    {
        $copies = array_map(static fn (int $i): Copy => new Copy('c'.$i, 'game'), range(1, Shelf::MAX_COPIES));
        self::assertCount(Shelf::MAX_COPIES, new Shelf('member', $copies)->copies());

        $this->expectExceptionMessageIsOrContains('A shelf holds at most 5000 copies.');

        new Shelf('member', [...$copies, new Copy('more', 'game')]);
    }

    public function testCopiesArePlacedReplacedAndRemoved(): void
    {
        $shelf = new Shelf('member')
            ->withCopy(new Copy('c1', 'harbour'))
            ->withCopy(new Copy('c2', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade], 'English'))
            ->withCopy(new Copy('c3', 'lanterns', [CopyStatus::Preordered]));

        self::assertSame(['c1', 'c2', 'c3'], array_map(static fn (Copy $copy): string => $copy->id, $shelf->copies()));
        self::assertSame(['c1', 'c2'], array_map(static fn (Copy $copy): string => $copy->id, $shelf->copiesOf('harbour')));
        self::assertSame(['c1', 'c2'], array_map(static fn (Copy $copy): string => $copy->id, $shelf->bringable()));
        self::assertSame('English', $shelf->copy('c2')?->edition);
        self::assertNull($shelf->copy('c9'));

        $shelf = $shelf->withCopy(new Copy('c1', 'harbour', [CopyStatus::PreviouslyOwned]))->withoutCopy('c2');

        self::assertSame(['c1', 'c3'], array_map(static fn (Copy $copy): string => $copy->id, $shelf->copies()));
        self::assertSame([CopyStatus::PreviouslyOwned], $shelf->copy('c1')?->statuses);
        self::assertSame([], $shelf->bringable());
    }

    public function testAMissingCopyIsNotRemoved(): void
    {
        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('The copy c1 is not on the shelf.');

        new Shelf('member')->withoutCopy('c1');
    }

    public function testAFullShelfTakesNoNewCopyButReplacesOne(): void
    {
        $shelf = new Shelf('member', array_map(static fn (int $i): Copy => new Copy('c'.$i, 'game'), range(1, Shelf::MAX_COPIES)));

        self::assertSame('French', $shelf->withCopy(new Copy('c1', 'game', edition: 'French'))->copy('c1')?->edition);

        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('A shelf holds at most 5000 copies.');

        $shelf->withCopy(new Copy('more', 'game'));
    }

    public function testWishesKeepTheMembersOrder(): void
    {
        $shelf = $this->wishes('a', 'b', 'c', 'd');

        self::assertSame(['a', 'b', 'c', 'd'], $this->games($shelf->wishes()));
        self::assertSame(['a', 'b', 'd', 'c'], $this->games($shelf->withWishBefore('d', 'c')->wishes()));
        self::assertSame(['d', 'a', 'b', 'c'], $this->games($shelf->withWishBefore('d', 'a')->wishes()));
        self::assertSame(['b', 'a', 'c', 'd'], $this->games($shelf->withWishAfter('a', 'b')->wishes()));
        self::assertSame(['b', 'c', 'd', 'a'], $this->games($shelf->withWishAfter('a', 'd')->wishes()));
        self::assertSame(['a', 'c', 'b', 'd'], $this->games($shelf->withWishBefore('c', 'b')->wishes()));
        self::assertSame(['a', 'c', 'b', 'd'], $this->games($shelf->withWishAfter('b', 'c')->wishes()));
    }

    public function testAWishReplacedKeepsItsPlace(): void
    {
        $shelf = $this->wishes('a', 'b', 'c')->withWish(new Wish('a', level: WishLevel::LikeToHave));

        self::assertSame(['a', 'b', 'c'], $this->games($shelf->wishes()));
        self::assertSame(WishLevel::LikeToHave, $shelf->wish('a')?->level);
        self::assertNull($shelf->wish('z'));
        self::assertSame(['b', 'c'], $this->games($shelf->withoutWish('a')->wishes()));
    }

    public function testTheWishlistGoesByLevelThenByTheMembersOrder(): void
    {
        $shelf = new Shelf('member', wishes: [
            new Wish('none-1'),
            new Wish('dont', level: WishLevel::DontBuy),
            new Wish('like-1', level: WishLevel::LikeToHave),
            new Wish('must', level: WishLevel::MustHave),
            new Wish('none-2'),
            new Wish('like-2', level: WishLevel::LikeToHave),
            new Wish('love', level: WishLevel::LoveToHave),
            new Wish('think', level: WishLevel::ThinkingAboutIt),
        ]);

        self::assertSame(['must', 'love', 'like-1', 'like-2', 'think', 'dont', 'none-1', 'none-2'], $this->games($shelf->wishlist()));
    }

    public function testAMissingWishIsNotWithdrawn(): void
    {
        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('The game a is not wished.');

        new Shelf('member')->withoutWish('a');
    }

    public function testAWishMovesOnlyBesideAnotherWish(): void
    {
        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('The game z is not wished.');

        $this->wishes('a')->withWishAfter('a', 'z');
    }

    public function testAMissingWishDoesNotMove(): void
    {
        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('The game z is not wished.');

        $this->wishes('a')->withWishBefore('z', 'a');
    }

    public function testAWishDoesNotMoveBesideItself(): void
    {
        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('A wish moves before or after another wish.');

        $this->wishes('a', 'b')->withWishBefore('a', 'a');
    }

    public function testAFullWishlistTakesNoNewWishButReplacesOne(): void
    {
        $shelf = new Shelf('member', wishes: array_map(static fn (int $i): Wish => new Wish('g'.$i), range(1, Shelf::MAX_WISHES)));

        self::assertSame(WishLevel::MustHave, $shelf->withWish(new Wish('g1', level: WishLevel::MustHave))->wish('g1')?->level);

        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('A shelf holds at most 1000 wishes.');

        $shelf->withWish(new Wish('more'));
    }

    public function testAFullNoteShelfTakesNoNewNoteButReplacesOne(): void
    {
        $shelf = new Shelf('member', notes: array_map(static fn (int $i): Note => new Note('g'.$i, 5), range(1, Shelf::MAX_NOTES)));

        self::assertSame(7, $shelf->withNote(new Note('g1', 7))->note('g1')?->rating);

        $this->expectException(ShelfRefused::class);
        $this->expectExceptionMessageIsOrContains('A shelf holds at most 5000 notes.');

        $shelf->withNote(new Note('more', 5));
    }

    public function testTooManyWishesAreRefused(): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('A shelf holds at most 1000 wishes.');

        new Shelf('member', wishes: array_map(static fn (int $i): Wish => new Wish('g'.$i), range(0, Shelf::MAX_WISHES)));
    }

    public function testNotesAreKeptAndAnEmptyNoteDropsOne(): void
    {
        $shelf = new Shelf('member')->withNote(new Note('a', 8))->withNote(new Note('b', plays: 2));

        self::assertSame(['a', 'b'], array_map(static fn (Note $note): string => $note->game, $shelf->notes()));
        self::assertSame(8, $shelf->note('a')?->rating);

        $shelf = $shelf->withNote(new Note('a', 9))->withNote(new Note('b'))->withNote(new Note('c'));

        self::assertSame(['a'], array_map(static fn (Note $note): string => $note->game, $shelf->notes()));
        self::assertSame(9, $shelf->note('a')?->rating);
        self::assertNull($shelf->note('b'));
    }

    public function testAFullShelfTakesNoNewNoteButReplacesOne(): void
    {
        $shelf = new Shelf('member', notes: array_map(static fn (int $i): Note => new Note('g'.$i, plays: 1), range(1, Shelf::MAX_NOTES)));

        self::assertSame(2, $shelf->withNote(new Note('g1', plays: 2))->note('g1')?->plays);
        self::assertCount(Shelf::MAX_NOTES - 1, $shelf->withNote(new Note('g1'))->notes());

        $this->expectExceptionMessageIsOrContains('A shelf holds at most 5000 notes.');

        $shelf->withNote(new Note('more', plays: 1));
    }

    public function testTooManyNotesAreRefused(): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('A shelf holds at most 5000 notes.');

        new Shelf('member', notes: array_map(static fn (int $i): Note => new Note('g'.$i, plays: 1), range(0, Shelf::MAX_NOTES)));
    }

    public function testAChangeKeepsTheRestOfTheShelf(): void
    {
        $shelf = new Shelf('member', [new Copy('c1', 'a'), new Copy('c0', 'z')], [new Wish('b'), new Wish('y')], [new Note('a', 3), new Note('x', 1)], Visibility::Public, Visibility::Members, 4);

        foreach ([
            $shelf->withCopy(new Copy('c2', 'a')),
            $shelf->withoutCopy('c0'),
            $shelf->withWish(new Wish('c')),
            $shelf->withoutWish('y'),
            $shelf->withWishAfter('b', 'y'),
            $shelf->withNote(new Note('b', 1)),
            $shelf->withNote(new Note('x')),
        ] as $changed) {
            self::assertSame('member', $changed->member);
            self::assertSame(Visibility::Public, $changed->shelfVisibility);
            self::assertSame(Visibility::Members, $changed->wishlistVisibility);
            self::assertSame(4, $changed->version);
            self::assertNotNull($changed->copy('c1'));
            self::assertNotNull($changed->wish('b'));
            self::assertNotNull($changed->note('a'));
        }

        $shared = $shelf->withVisibility(Visibility::Private, Visibility::Public);
        self::assertSame([Visibility::Private, Visibility::Public, 4], [$shared->shelfVisibility, $shared->wishlistVisibility, $shared->version]);
        self::assertCount(2, $shared->copies());
        self::assertCount(2, $shared->wishes());
        self::assertCount(2, $shared->notes());

        $versioned = $shelf->withVersion(5);
        self::assertSame([Visibility::Public, Visibility::Members, 5], [$versioned->shelfVisibility, $versioned->wishlistVisibility, $versioned->version]);
        self::assertCount(2, $versioned->copies());
        self::assertCount(2, $versioned->wishes());
        self::assertCount(2, $versioned->notes());
    }

    public function testTheOwnerSeesTheWholeShelf(): void
    {
        $shelf = new Shelf('member', [new Copy('c1', 'a', location: 'Attic')], [new Wish('b'), new Wish('c', level: WishLevel::MustHave)], [new Note('a', 3)]);

        $view = $shelf->view(Viewer::Owner);

        self::assertSame('member', $view->member);
        self::assertSame(Viewer::Owner, $view->viewer);
        self::assertSame('Attic', $view->copies[0]->location);
        self::assertSame(['c', 'b'], $this->games($view->wishes));
        self::assertSame(3, $view->notes[0]->rating);
    }

    public function testAnotherViewerSeesWhatTheMemberShows(): void
    {
        $shelf = new Shelf('member', [new Copy('c1', 'a', edition: 'French', location: 'Attic')], [new Wish('b')], [new Note('a', 3)]);

        $private = $shelf->view(Viewer::Member);
        self::assertSame([[], [], []], [$private->copies, $private->wishes, $private->notes]);
        self::assertSame(Viewer::Member, $private->viewer);

        $members = $shelf->withVisibility(Visibility::Members, Visibility::Private);
        $seen = $members->view(Viewer::Member);
        self::assertSame('French', $seen->copies[0]->edition);
        self::assertNull($seen->copies[0]->location);
        self::assertSame([], $seen->wishes);
        self::assertSame([], $seen->notes);
        self::assertSame([], $members->view(Viewer::Anyone)->copies);

        $public = $shelf->withVisibility(Visibility::Private, Visibility::Public)->view(Viewer::Anyone);
        self::assertSame([], $public->copies);
        self::assertSame(['b'], $this->games($public->wishes));
        self::assertSame([], $public->notes);
    }
}
