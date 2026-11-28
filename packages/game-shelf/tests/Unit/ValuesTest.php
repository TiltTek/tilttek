<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\CollectionStatus;
use Tilttek\GameShelf\BringableGame;
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Note;
use Tilttek\GameShelf\Viewer;
use Tilttek\GameShelf\Visibility;
use Tilttek\GameShelf\Wish;
use Tilttek\GameShelf\WishKind;
use Tilttek\GameShelf\WishLevel;

#[CoversClass(Copy::class)]
#[CoversClass(CopyStatus::class)]
#[CoversClass(Wish::class)]
#[CoversClass(WishKind::class)]
#[CoversClass(WishLevel::class)]
#[CoversClass(Note::class)]
#[CoversClass(Visibility::class)]
#[CoversClass(Viewer::class)]
#[CoversClass(BringableGame::class)]
final class ValuesTest extends TestCase
{
    public function testACopyIsOwnedAndBringableByDefault(): void
    {
        $copy = new Copy('copy-1', 'game@x');

        self::assertSame('copy-1', $copy->id);
        self::assertSame('game@x', $copy->game);
        self::assertSame([CopyStatus::Owned], $copy->statuses);
        self::assertNull($copy->edition);
        self::assertNull($copy->location);
        self::assertTrue($copy->bringable);
        self::assertTrue($copy->canBeBrought());
        self::assertTrue($copy->has(CopyStatus::Owned));
        self::assertFalse($copy->has(CopyStatus::ForTrade));
    }

    public function testACopyKeepsItsStatusesInTheOrderOfTheEnumeration(): void
    {
        $copy = new Copy('copy-1', 'game', [CopyStatus::ForTrade, CopyStatus::Preordered, CopyStatus::Owned], ' French ', ' Attic, top ', false);

        self::assertSame([CopyStatus::Owned, CopyStatus::Preordered, CopyStatus::ForTrade], $copy->statuses);
        self::assertSame('French', $copy->edition);
        self::assertSame('Attic, top', $copy->location);
        self::assertFalse($copy->canBeBrought());
    }

    public function testACopyNotOwnedCannotBeBrought(): void
    {
        self::assertFalse(new Copy('copy-1', 'game', [CopyStatus::Preordered])->canBeBrought());
        self::assertFalse(new Copy('copy-1', 'game', [CopyStatus::PreviouslyOwned])->canBeBrought());
    }

    public function testACopyChangesItsStatusesAndKeepsTheRest(): void
    {
        $copy = new Copy('copy-1', 'game', [CopyStatus::Owned], 'French', 'Attic', false)->withStatuses([CopyStatus::PreviouslyOwned]);

        self::assertSame([CopyStatus::PreviouslyOwned], $copy->statuses);
        self::assertSame(['copy-1', 'game', 'French', 'Attic', false], [$copy->id, $copy->game, $copy->edition, $copy->location, $copy->bringable]);
    }

    public function testACopyLosesItsLocationForAnotherViewer(): void
    {
        $copy = new Copy('copy-1', 'game', [CopyStatus::Owned, CopyStatus::ForTrade], 'French', 'Attic', false)->withoutPrivateDetails();

        self::assertNull($copy->location);
        self::assertSame(['copy-1', 'game', [CopyStatus::Owned, CopyStatus::ForTrade], 'French', false], [$copy->id, $copy->game, $copy->statuses, $copy->edition, $copy->bringable]);
    }

    /**
     * @return iterable<string, array{list<CopyStatus>, string}>
     */
    public static function badStatuses(): iterable
    {
        yield 'none' => [[], 'A copy holds at least one status, each once.'];
        yield 'twice' => [[CopyStatus::Owned, CopyStatus::Owned], 'A copy holds at least one status, each once.'];
        yield 'owned before and now' => [[CopyStatus::Owned, CopyStatus::PreviouslyOwned], 'A copy is owned or previously owned, not both.'];
        yield 'for trade, not owned' => [[CopyStatus::ForTrade, CopyStatus::Preordered], 'A copy for trade is owned.'];
    }

    /**
     * @param list<CopyStatus> $statuses
     */
    #[DataProvider('badStatuses')]
    public function testACopyRefusesStatusesThatDoNotGoTogether(array $statuses, string $message): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Copy('copy-1', 'game', $statuses);
    }

    public function testACopyRefusesAnIdentifierOutOfItsShape(): void
    {
        $this->expectExceptionMessageIsOrContains('The identifier of a copy is');

        new Copy('1', 'game');
    }

    public function testACopyRefusesAGameOutOfItsShape(): void
    {
        $this->expectExceptionMessageIsOrContains('The identifier of a game is');

        new Copy('copy', '1');
    }

    public function testACopyBoundsItsEdition(): void
    {
        self::assertSame(100, mb_strlen((string) new Copy('c', 'g', edition: str_repeat('é', 100))->edition));
        $this->expectExceptionMessageIsOrContains('The edition of a copy is 1 to 100 characters.');

        new Copy('c', 'g', edition: str_repeat('é', 101));
    }

    public function testACopyBoundsItsLocation(): void
    {
        self::assertSame(100, mb_strlen((string) new Copy('c', 'g', location: str_repeat('é', 100))->location));
        $this->expectExceptionMessageIsOrContains('The location of a copy is 1 to 100 characters.');

        new Copy('c', 'g', location: str_repeat('é', 101));
    }

    public function testTheStatusesOfACollectionAreSplitBetweenCopiesAndWishes(): void
    {
        self::assertSame(CopyStatus::Owned, CopyStatus::fromCollection(CollectionStatus::Owned));
        self::assertSame(CopyStatus::PreviouslyOwned, CopyStatus::fromCollection(CollectionStatus::PreviouslyOwned));
        self::assertSame(CopyStatus::Preordered, CopyStatus::fromCollection(CollectionStatus::Preordered));
        self::assertSame(CopyStatus::ForTrade, CopyStatus::fromCollection(CollectionStatus::ForTrade));
        self::assertSame(WishKind::Wishlist, WishKind::fromCollection(CollectionStatus::Wishlist));
        self::assertSame(WishKind::WantToBuy, WishKind::fromCollection(CollectionStatus::WantToBuy));
        self::assertSame(WishKind::WantInTrade, WishKind::fromCollection(CollectionStatus::WantInTrade));
        self::assertSame(WishKind::WantToPlay, WishKind::fromCollection(CollectionStatus::WantToPlay));

        foreach (CollectionStatus::cases() as $status) {
            self::assertTrue(!CopyStatus::fromCollection($status) instanceof CopyStatus xor !WishKind::fromCollection($status) instanceof WishKind);
        }
    }

    public function testAWishIsOnTheWishlistByDefault(): void
    {
        $wish = new Wish('game');

        self::assertSame('game', $wish->game);
        self::assertSame([WishKind::Wishlist], $wish->kinds);
        self::assertNull($wish->level);
        self::assertTrue($wish->has(WishKind::Wishlist));
        self::assertFalse($wish->has(WishKind::WantToPlay));
    }

    public function testAWishKeepsItsKindsInTheOrderOfTheEnumeration(): void
    {
        $wish = new Wish('game', [WishKind::WantToPlay, WishKind::Wishlist], WishLevel::MustHave);

        self::assertSame([WishKind::Wishlist, WishKind::WantToPlay], $wish->kinds);
        self::assertSame(WishLevel::MustHave, $wish->level);
    }

    /**
     * @return iterable<string, array{list<WishKind>}>
     */
    public static function badKinds(): iterable
    {
        yield 'none' => [[]];
        yield 'twice' => [[WishKind::WantToBuy, WishKind::WantToBuy]];
    }

    /**
     * @param list<WishKind> $kinds
     */
    #[DataProvider('badKinds')]
    public function testAWishRefusesKindsOutOfItsShape(array $kinds): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains('A wish holds at least one kind, each once.');

        new Wish('game', $kinds);
    }

    public function testAWishRefusesAGameOutOfItsShape(): void
    {
        $this->expectExceptionMessageIsOrContains('The identifier of a game is');

        new Wish('');
    }

    public function testTheLevelsGoFromMustHaveToDontBuy(): void
    {
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn (WishLevel $level): int => $level->value, WishLevel::cases()));
        self::assertSame(WishLevel::MustHave, WishLevel::cases()[0]);
        self::assertSame(WishLevel::DontBuy, WishLevel::from(5));
    }

    public function testANoteKeepsWhatTheMemberWrote(): void
    {
        $note = new Note('game', 10, " Great\r\nwith four ", ['family', 'short'], 1_000_000);

        self::assertSame(['game', 10, "Great\nwith four", ['family', 'short'], 1_000_000], [$note->game, $note->rating, $note->comment, $note->tags, $note->plays]);
        self::assertFalse($note->isEmpty());
        self::assertSame(1, new Note('game', 1)->rating);
    }

    public function testANoteIsEmptyWhenItKeepsNothing(): void
    {
        self::assertTrue(new Note('game')->isEmpty());
        self::assertFalse(new Note('game', rating: 5)->isEmpty());
        self::assertFalse(new Note('game', comment: 'Fun')->isEmpty());
        self::assertFalse(new Note('game', tags: ['a'])->isEmpty());
        self::assertFalse(new Note('game', plays: 1)->isEmpty());
    }

    public function testANoteChangesItsPlaysAndKeepsTheRest(): void
    {
        $note = new Note('game', 7, 'Fun', ['a'], 2)->withPlays(3);

        self::assertSame(['game', 7, 'Fun', ['a'], 3], [$note->game, $note->rating, $note->comment, $note->tags, $note->plays]);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function badNumbers(): iterable
    {
        yield 'rating 0' => [0, 0];
        yield 'rating 11' => [11, 0];
        yield 'plays below 0' => [5, -1];
        yield 'plays above the most' => [5, 1_000_001];
    }

    #[DataProvider('badNumbers')]
    public function testANoteRefusesNumbersOutOfTheirBounds(int $rating, int $plays): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageMatches('/^(A rating is 1 to 10\.|A game is played 0 to 1000000 times\.)$/');

        new Note('game', $rating, plays: $plays);
    }

    public function testANoteBoundsItsComment(): void
    {
        self::assertSame(2_000, mb_strlen((string) new Note('game', comment: str_repeat('é', 2_000))->comment));
        $this->expectExceptionMessageIsOrContains('The comment of a note is 1 to 2000 characters.');

        new Note('game', comment: str_repeat('é', 2_001));
    }

    public function testANoteBoundsItsTags(): void
    {
        $tags = array_map(static fn (int $i): string => 't'.$i, range(1, 20));
        self::assertCount(20, new Note('game', tags: $tags)->tags);
        self::assertSame([str_repeat('é', 40)], new Note('game', tags: [str_repeat('é', 40)])->tags);
        self::assertSame(['a b'], new Note('game', tags: [' a b '])->tags);

        $this->expectExceptionMessageIsOrContains('A note holds at most 20 tags.');

        new Note('game', tags: [...$tags, 't21']);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function badTags(): iterable
    {
        yield 'too long' => [[str_repeat('a', 41)], 'The tag is 1 to 40 characters.'];
        yield 'a semicolon' => [['a;b'], 'A tag holds no ";".'];
        yield 'twice' => [['a', ' a'], 'The tag "a" is there twice.'];
    }

    /**
     * @param list<string> $tags
     */
    #[DataProvider('badTags')]
    public function testANoteRefusesTagsOutOfTheirShape(array $tags, string $message): void
    {
        $this->expectException(InvalidShelf::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Note('game', tags: $tags);
    }

    public function testANoteRefusesAGameOutOfItsShape(): void
    {
        $this->expectExceptionMessageIsOrContains('The identifier of a game is');

        new Note('-');
    }

    public function testAVisibilityShowsAPartToItsViewers(): void
    {
        self::assertSame([true, false, false], array_map(Visibility::Private->shows(...), Viewer::cases()));
        self::assertSame([true, true, false], array_map(Visibility::Members->shows(...), Viewer::cases()));
        self::assertSame([true, true, true], array_map(Visibility::Public->shows(...), Viewer::cases()));
    }

    public function testABringableGameNamesTheGameAndItsOwner(): void
    {
        $game = new BringableGame('game', 'member');

        self::assertSame(['game', 'member'], [$game->game, $game->owner]);
    }
}
