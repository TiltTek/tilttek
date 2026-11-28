<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit\Choice;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Ballot;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\PlayerCountChoice;
use Tilttek\GameCatalog\Polls;
use Tilttek\GameCatalog\Range;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\GameNight\Choice\Criteria;
use Tilttek\GameNight\Choice\GameChooser;
use Tilttek\GameNight\Choice\OwnedGame;
use Tilttek\GameNight\Choice\Suggestion;
use Tilttek\GameNight\Contract\ShelfReader;
use Tilttek\GameNight\Tests\Unit\Fixture\ListedShelves;

#[CoversClass(GameChooser::class)]
#[CoversClass(Suggestion::class)]
final class GameChooserTest extends TestCase
{
    private Catalog $catalog;

    private Polls $polls;

    #[Override]
    protected function setUp(): void
    {
        $store = new InMemoryCatalogStore();
        $this->catalog = new Catalog($store, new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC')));
        $this->polls = new Polls($store);
    }

    private function game(string $id, string $name, ?Range $players, ?Range $playtime): void
    {
        $this->catalog->add($id, new Details($name, players: $players, playtime: $playtime));
    }

    /**
     * @param list<string>     $names
     * @param list<Suggestion> $suggestions
     */
    private function assertNames(array $names, array $suggestions): void
    {
        self::assertSame($names, array_map(static fn (Suggestion $suggestion): string => $suggestion->name, $suggestions));
    }

    public function testAGameIsSuggestedForThePlayersAndTheMinutes(): void
    {
        $this->game('g-fits', 'Fits', new Range(2, 4), new Range(30, 60));
        $this->game('g-crowd', 'Crowd', new Range(5, 10), new Range(30, 60));
        $this->game('g-few', 'Few', new Range(1, 3), new Range(30, 60));
        $this->game('g-long', 'Long', new Range(2, 4), new Range(60, 61));
        $this->game('g-unknown', 'Unknown', null, new Range(30, 60));
        $this->game('g-edge', 'Edge', new Range(4, 4), new Range(60, 60));
        $this->catalog->hide($this->catalog->add('g-hidden', new Details('Hidden', players: new Range(2, 4)))->id);
        $chooser = new GameChooser($this->catalog, new ListedShelves());

        $suggestions = $chooser->suggest(new Criteria(4, 60, [], ['g-fits', 'g-crowd', 'g-few', 'g-long', 'g-unknown', 'g-edge', 'g-hidden', 'g-missing']));

        $this->assertNames(['Edge', 'Fits'], $suggestions);
        self::assertTrue($suggestions[0]->inLibrary);
        self::assertSame([], $suggestions[0]->owners);
        self::assertTrue($suggestions[1]->players->equals(new Range(2, 4)));
        self::assertTrue($suggestions[1]->playtime?->equals(new Range(30, 60)));
        self::assertSame('g-fits', $suggestions[1]->game);
    }

    public function testTheGamesOfTheMembersAreReadFromTheirShelves(): void
    {
        $this->game('g-1', 'Alpha', new Range(2, 4), new Range(30, 45));
        $this->game('g-2', 'Beta', new Range(2, 4), new Range(30, 45));
        $shelves = new ListedShelves([
            new OwnedGame('g-2', 'm-1'),
            new OwnedGame('g-2', 'm-1'),
            new OwnedGame('g-2', 'm-2'),
            new OwnedGame('g-1', 'm-2'),
            new OwnedGame('g-1', 'm-stranger'),
        ]);

        $suggestions = new GameChooser($this->catalog, $shelves)->suggest(new Criteria(3, 120, ['m-1', 'm-2']));

        self::assertSame([[['m-1', 'm-2'], GameChooser::MAX_SHELF_GAMES]], $shelves->asked);
        $this->assertNames(['Beta', 'Alpha'], $suggestions);
        self::assertSame(['m-1', 'm-2'], $suggestions[0]->owners);
        self::assertSame(['m-2'], $suggestions[1]->owners);
        self::assertFalse($suggestions[0]->inLibrary);
    }

    public function testTheShelvesAreNotAskedWithoutMembers(): void
    {
        $shelves = new ListedShelves([new OwnedGame('g-1', 'm-1')]);

        self::assertSame([], new GameChooser($this->catalog, $shelves)->suggest(new Criteria(3, 120)));
        self::assertSame([], $shelves->asked);
    }

    public function testTheShelvesAreReadUpToTheirBound(): void
    {
        $this->game('g-last', 'Last', new Range(2, 4), new Range(30, 45));
        $this->game('g-first', 'First', new Range(2, 4), new Range(30, 45));
        $games = array_fill(0, GameChooser::MAX_SHELF_GAMES - 1, new OwnedGame('g-first', 'm-1'));
        $games[] = new OwnedGame('g-last', 'm-1');
        $over = [...$games, new OwnedGame('g-over', 'm-1')];
        $this->game('g-over', 'Over', new Range(2, 4), new Range(30, 45));

        $this->assertNames(['First', 'Last'], new GameChooser($this->catalog, new ListedShelves($over))->suggest(new Criteria(3, 120, ['m-1'])));
    }

    public function testTheShelvesAreNotReadPastTheirBound(): void
    {
        $this->game('g-1', 'Alpha', new Range(2, 4), new Range(30, 45));
        $shelves = new class implements ShelfReader {
            public int $read = 0;

            #[Override]
            public function games(array $members, int $limit): iterable
            {
                for ($i = 0; $i < GameChooser::MAX_SHELF_GAMES + 50; ++$i) {
                    ++$this->read;

                    yield new OwnedGame('g-1', 'm-1');
                }
            }
        };

        new GameChooser($this->catalog, $shelves)->suggest(new Criteria(3, 120, ['m-1']));

        self::assertSame(GameChooser::MAX_SHELF_GAMES + 1, $shelves->read);
    }

    public function testTheGamesTheClubKeepsCountAsOneMoreWhoCanBringThem(): void
    {
        $this->game('g-1', 'Alpha', new Range(2, 4), new Range(30, 45));
        $this->game('g-2', 'Beta', new Range(2, 4), new Range(30, 45));
        $shelves = new ListedShelves([new OwnedGame('g-1', 'm-1'), new OwnedGame('g-2', 'm-1')]);

        $suggestions = new GameChooser($this->catalog, $shelves)->suggest(new Criteria(3, 120, ['m-1'], ['g-2']));

        $this->assertNames(['Beta', 'Alpha'], $suggestions);
    }

    public function testTheGamesVotedBestComeFirstWithTheirVotes(): void
    {
        $this->game('g-best', 'Zeta', new Range(2, 6), new Range(30, 60));
        $this->game('g-rec', 'Yota', new Range(2, 6), new Range(30, 60));
        $this->game('g-none', 'Xi', new Range(2, 6), new Range(30, 60));
        $this->game('g-not', 'Alpha', new Range(2, 6), new Range(30, 60));
        $this->catalog->addIdentifier('g-best', ExternalId::boardGameGeek(13));
        $this->polls->vote(new Ballot('g-best', 'v-1', [4 => 5], [4 => PlayerCountChoice::Best]));
        $this->polls->vote(new Ballot('g-best', 'v-2', [4 => 4], [4 => PlayerCountChoice::Best]));
        $this->polls->vote(new Ballot('g-best', 'v-3', [], [4 => PlayerCountChoice::Recommended]));
        $this->polls->vote(new Ballot('g-rec', 'v-1', [], [4 => PlayerCountChoice::Recommended]));
        $this->polls->vote(new Ballot('g-not', 'v-1', [], [4 => PlayerCountChoice::NotRecommended]));
        $this->polls->vote(new Ballot('g-none', 'v-1', [3 => 5]));

        $suggestions = new GameChooser($this->catalog, new ListedShelves())->suggest(new Criteria(4, 60, [], ['g-not', 'g-none', 'g-rec', 'g-best']));

        $this->assertNames(['Zeta', 'Yota', 'Xi', 'Alpha'], $suggestions);
        self::assertSame(PlayerCountChoice::Best, $suggestions[0]->choice);
        self::assertSame(3, $suggestions[0]->choiceVotes);
        self::assertSame(450, $suggestions[0]->averageRating);
        self::assertSame(2, $suggestions[0]->ratingVotes);
        self::assertSame('13', $suggestions[0]->boardGameGeek?->value);
        self::assertSame('https://boardgamegeek.com/boardgame/13', $suggestions[0]->boardGameGeekLink());
        self::assertNull($suggestions[1]->boardGameGeekLink());
        self::assertNull($suggestions[2]->choice);
        self::assertSame(0, $suggestions[2]->choiceVotes);
        self::assertNull($suggestions[2]->averageRating);
        self::assertSame(0, $suggestions[2]->ratingVotes);
        self::assertSame(PlayerCountChoice::NotRecommended, $suggestions[3]->choice);
    }

    public function testKnownMinutesComeBeforeUnknownThenTheMostOwnersThenTheName(): void
    {
        $this->game('g-a', 'Bravo', new Range(2, 4), null);
        $this->game('g-b', 'Delta', new Range(2, 4), new Range(20, 30));
        $this->game('g-c', 'Charlie', new Range(2, 4), new Range(20, 30));
        $this->game('g-d', 'Echo', new Range(2, 4), new Range(20, 30));
        $this->game('g-e', 'Charlie', new Range(2, 4), new Range(20, 30));
        $shelves = new ListedShelves([new OwnedGame('g-d', 'm-1'), new OwnedGame('g-d', 'm-2'), new OwnedGame('g-d', 'm-3'), new OwnedGame('g-b', 'm-1'), new OwnedGame('g-a', 'm-1'), new OwnedGame('g-a', 'm-2'), new OwnedGame('g-a', 'm-3')]);

        $suggestions = new GameChooser($this->catalog, $shelves)->suggest(new Criteria(2, 30, ['m-1', 'm-2', 'm-3'], ['g-b', 'g-e', 'g-c']));

        self::assertSame(['g-d', 'g-b', 'g-c', 'g-e', 'g-a'], array_map(static fn (Suggestion $suggestion): string => $suggestion->game, $suggestions));
        self::assertNull($suggestions[4]->playtime);
    }

    public function testTheSuggestionsStopAtTheLimit(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $this->game('g-'.$name, $name, new Range(1, 4), new Range(10, 20));
        }

        $this->assertNames(['A', 'B'], new GameChooser($this->catalog, new ListedShelves())->suggest(new Criteria(2, 30, [], ['g-C', 'g-B', 'g-A'], 2)));
    }
}
