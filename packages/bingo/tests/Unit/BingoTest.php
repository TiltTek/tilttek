<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Bingo\Activity\BingoActivityObserver;
use Tilttek\Bingo\Bingo;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;
use Tilttek\Bingo\CardDealer;
use Tilttek\Bingo\Claim;
use Tilttek\Bingo\Event\BingoWon;
use Tilttek\Bingo\Event\SquareCalled;
use Tilttek\Bingo\Event\SquareTicked;
use Tilttek\Bingo\Exception\BingoConflict;
use Tilttek\Bingo\Exception\BingoRefused;
use Tilttek\Bingo\Grid;
use Tilttek\Bingo\Square;
use Tilttek\Bingo\SquarePool;
use Tilttek\Bingo\Store\InMemoryBingoStore;
use Tilttek\Bingo\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Bingo\Tests\Unit\Fixture\Games;
use Tilttek\Bingo\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Bingo\Tests\Unit\Fixture\ScriptedStore;
use Tilttek\Bingo\TickMode;
use Tilttek\Bingo\Trigger;
use Tilttek\Bingo\WinPattern;
use Tilttek\Bingo\WinPatterns;

#[CoversClass(Bingo::class)]
#[CoversClass(Claim::class)]
#[CoversClass(BingoActivityObserver::class)]
#[CoversClass(InMemoryBingoStore::class)]
#[CoversClass(BingoWon::class)]
#[CoversClass(SquareTicked::class)]
#[CoversClass(SquareCalled::class)]
#[CoversClass(BingoConflict::class)]
final class BingoTest extends TestCase
{
    private FrozenClock $clock;

    private RecordingDispatcher $events;

    private ScriptedStore $store;

    private Bingo $bingo;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->events = new RecordingDispatcher();
        $this->store = new ScriptedStore();
        $this->bingo = new Bingo($this->store, new CardDealer(Games::SECRET), $this->events, $this->clock);
    }

    public function testGesturesTickTheCardAndACompletedRowWinsOnItsOwn(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('con-2026', Games::pool(12), $grid, WinPatterns::line($grid));
        $card = $this->bingo->deal('con-2026', 'member:1');

        self::assertSame(1, $card->serial);
        self::assertSame(['s11', 's5', 's1', 's12', null, 's9', 's3', 's4', 's6'], $card->layout);
        self::assertSame($card->serial, $this->bingo->deal('con-2026', 'member:1')->serial);
        self::assertSame(1, $this->bingo->game('con-2026')->dealt());

        $observer = new BingoActivityObserver($this->bingo);
        $observer->appended($this->entry(1, 'k1', 'booth:11'));
        $observer->appended($this->entry(2, 'k2', 'booth:2'));
        $observer->appended($this->entry(3, 'k3', 'booth:5'));
        self::assertEquals([new SquareTicked('con-2026', 1, 's11'), new SquareTicked('con-2026', 1, 's5')], $this->events->events);
        self::assertSame([0, 1, 4], $this->bingo->claim('con-2026', 1, 'member:1')->ticked);
        self::assertFalse($this->bingo->claim('con-2026', 1, 'member:1')->isWon());

        $observer->appended($this->entry(4, 'k4', 'booth:1'));

        self::assertEquals([
            new SquareTicked('con-2026', 1, 's11'),
            new SquareTicked('con-2026', 1, 's5'),
            new SquareTicked('con-2026', 1, 's1'),
            new BingoWon('con-2026', 1, 'member:1', 'row'),
        ], $this->events->events);
        $won = $this->bingo->card('con-2026', 1);
        self::assertSame('row', $won->pattern());
        self::assertEquals($this->clock->now, $won->wonAt());
        self::assertSame([1], $this->bingo->game('con-2026')->winners());

        $claim = $this->bingo->claim('con-2026', 1, 'member:1');
        self::assertEquals(new Claim('con-2026', 1, [0, 1, 2, 4], 'row'), $claim);
        self::assertTrue($claim->isWon());

        $observer->appended($this->entry(5, 'k5', 'booth:12'));
        $observer->appended($this->entry(6, 'k6', 'booth:9'));
        self::assertCount(1, array_filter($this->events->events, static fn (object $event): bool => $event instanceof BingoWon));
        self::assertSame([1], $this->bingo->game('con-2026')->winners());
    }

    public function testACancelledGestureUnticksItsSquare(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('con-2026', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->bingo->deal('con-2026', 'member:1');

        $observer = new BingoActivityObserver($this->bingo);
        $observer->appended($this->entry(1, 'k1', 'booth:11'));

        $saves = $this->store->cardSaves;

        $observer->appended($this->entry(2, 'k9', 'booth:11', EntryKind::Cancelled));
        self::assertSame($saves, $this->store->cardSaves);
        $observer->appended($this->entry(3, 'k1', 'booth:11', EntryKind::Cancelled));

        self::assertSame([], $this->bingo->card('con-2026', 1)->ticks());
        self::assertSame($saves + 1, $this->store->cardSaves);
    }

    public function testGesturesTickOnlyTheOpenGamesTickedByActivityWhereTheSquareIsOnTheCard(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('closed', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->bingo->create('called', Games::pool(12), $grid, WinPatterns::line($grid), TickMode::Called);
        $this->bingo->create('open', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->bingo->deal('closed', 'member:1');
        $this->bingo->deal('called', 'member:1');
        $this->bingo->deal('open', 'member:1');
        $this->bingo->close('closed');

        $saves = $this->store->cardSaves;

        $this->bingo->record(Activity::of('k1', 'member:1', 'booth.visited', $this->clock->now(), 'booth:999'));
        $this->bingo->record(Activity::of('k2', 'member:2', 'booth.visited', $this->clock->now(), 'booth:1'));
        self::assertSame($saves, $this->store->cardSaves);

        $open = $this->bingo->card('open', 1);
        $missing = array_values(array_diff(array_map(static fn (int $n): string => 's'.$n, range(1, 12)), $open->layout));
        $this->bingo->record(Activity::of('k3', 'member:1', 'booth.visited', $this->clock->now(), 'booth:'.substr($missing[0], 1)));
        self::assertSame($saves, $this->store->cardSaves);

        $present = (string) $open->layout[0];
        $this->bingo->record(Activity::of('k4', 'member:1', 'booth.visited', $this->clock->now(), 'booth:'.substr($present, 1)));

        self::assertSame($saves + 1, $this->store->cardSaves);
        self::assertSame([], $this->bingo->card('closed', 1)->ticks());
        self::assertSame([], $this->bingo->card('called', 1)->ticks());
        self::assertSame([0 => ['k4']], $this->bingo->card('open', 1)->ticks());
    }

    public function testASquareWithoutATriggerIsNeverTickedByAGesture(): void
    {
        $grid = new Grid(3, false);
        $pool = new SquarePool(array_map(static fn (int $n): Square => new Square('s'.$n, 'S'), range(1, 9)));
        $this->bingo->create('g', $pool, $grid, WinPatterns::line($grid));
        $this->bingo->deal('g', 'member:1');

        $this->bingo->record(Activity::of('k1', 'member:1', 'booth.visited', $this->clock->now(), 'booth:1'));

        self::assertSame([], $this->bingo->card('g', 1)->ticks());
    }

    public function testACalledGameWinsOnlyByAClaimTheCalledSquaresBackUp(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('night', Games::pool(12), $grid, new WinPatterns(WinPattern::diagonal($grid)), TickMode::Called);
        $card = $this->bingo->deal('night', 'member:1');
        [$first, $last] = [(string) $card->layout[0], (string) $card->layout[8]];

        $this->bingo->call('night', $first);
        $this->bingo->call('night', $first);
        self::assertEquals([new SquareCalled('night', $first)], $this->events->events);
        self::assertEquals(new Claim('night', 1, [0, 4], null), $this->bingo->claim('night', 1, 'member:1'));

        $this->bingo->call('night', $last);
        $claim = $this->bingo->claim('night', 1, 'member:1');

        self::assertEquals(new Claim('night', 1, [0, 4, 8], 'diagonal'), $claim);
        self::assertEquals($claim, $this->bingo->claim('night', 1, 'member:1'));
        self::assertEquals([
            new SquareCalled('night', $first),
            new SquareCalled('night', $last),
            new BingoWon('night', 1, 'member:1', 'diagonal'),
        ], $this->events->events);
    }

    public function testAClaimOnAnotherPlayersCardIsRefused(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('night', Games::pool(12), $grid, WinPatterns::line($grid), TickMode::Called);
        $this->bingo->deal('night', 'member:1');

        $this->expectException(BingoRefused::class);
        $this->expectExceptionMessageIsOrContains('The card 1 of the bingo game night is not the card of this player.');
        $this->bingo->claim('night', 1, 'member:2');
    }

    public function testACardThatDoesNotMatchItsSeedIsRefused(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('night', Games::pool(12), $grid, WinPatterns::line($grid), TickMode::Called);
        $this->store->inner->addCard(new Card('night', 7, 'member:1', ['s1', 's2', 's3', 's4', null, 's5', 's6', 's7', 's8']), 'forged');

        $this->expectException(BingoRefused::class);
        $this->expectExceptionMessageIsOrContains('The card 7 of the bingo game night does not match its seed.');
        $this->bingo->claim('night', 7, 'member:1');
    }

    public function testACardOfAClosedGameIsCheckedNeverWon(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('night', Games::pool(12), $grid, new WinPatterns(WinPattern::diagonal($grid)), TickMode::Called);
        $card = $this->bingo->deal('night', 'member:1');
        $this->bingo->call('night', (string) $card->layout[0]);
        $this->bingo->call('night', (string) $card->layout[8]);
        $this->bingo->close('night');

        self::assertEquals(new Claim('night', 1, [0, 4, 8], null), $this->bingo->verify('night', 1));
        self::assertFalse($this->bingo->card('night', 1)->hasWon());
    }

    public function testACardUnlikeTheOthersIsDealtPassingOverTakenSerials(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->store->takenFingerprints = 2;

        $card = $this->bingo->deal('g', 'member:1');

        self::assertSame(3, $card->serial);
        self::assertSame(3, $this->bingo->game('g')->dealt());
    }

    public function testDealingGivesUpAfterItsAttempts(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->store->takenFingerprints = Bingo::DEAL_ATTEMPTS;

        try {
            $this->bingo->deal('g', 'member:1');
            self::fail('A card was dealt with every fingerprint taken.');
        } catch (BingoRefused $bingoRefused) {
            self::assertSame('No card unlike the others was found for a player of the bingo game g.', $bingoRefused->getMessage());
        }

        self::assertSame(Bingo::DEAL_ATTEMPTS, $this->bingo->game('g')->dealt());
    }

    public function testACardDealtInBetweenIsTheOneGiven(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid));
        $other = new Card('g', 99, 'member:1', new CardDealer(Games::SECRET)->layout($this->bingo->game('g'), 99));
        $this->store->dealtInBetween = $other;

        self::assertSame(99, $this->bingo->deal('g', 'member:1')->serial);
    }

    public function testNoCardTwiceForThesSamePlayerOrSerialAndNoTwiceTheSameFingerprint(): void
    {
        $store = new InMemoryBingoStore();
        $grid = new Grid(3);
        $this->bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid));

        self::assertTrue($store->addCard(new Card('g', 1, 'member:1', ['a']), 'f1'));
        self::assertFalse($store->addCard(new Card('g', 2, 'member:2', ['a']), 'f1'));
        self::assertNull($store->cardOf('g', 'member:2'));
        self::assertTrue($store->addCard(new Card('h', 1, 'member:2', ['a']), 'f1'));

        foreach ([new Card('g', 1, 'member:3', ['a']), new Card('g', 3, 'member:1', ['a'])] as $card) {
            try {
                $store->addCard($card, 'f9');
                self::fail('A card was kept twice.');
            } catch (BingoConflict $exception) {
                self::assertStringStartsWith('The card g/', $exception->getMessage());
            }
        }

        self::assertNull($store->card('g', 9));
        self::assertNull($store->game('nope'));
    }

    public function testConflictsAreReadAgainAndMadeAgainThenLetThrough(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('night', Games::pool(12), $grid, WinPatterns::line($grid), TickMode::Called);
        $this->store->gameConflicts = Bingo::ATTEMPTS - 1;
        $this->bingo->call('night', 's1');
        self::assertSame(['s1'], $this->bingo->game('night')->called());
        self::assertSame(Bingo::ATTEMPTS, $this->store->gameSaves);

        $this->store->gameConflicts = Bingo::ATTEMPTS;

        try {
            $this->bingo->call('night', 's2');
            self::fail('Endless conflicts were hidden.');
        } catch (BingoConflict) {
            self::assertSame(2 * Bingo::ATTEMPTS, $this->store->gameSaves);
        }
    }

    public function testCardConflictsAreReadAgainAndMadeAgainThenLetThrough(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('con', Games::pool(12), $grid, WinPatterns::line($grid));
        $card = $this->bingo->deal('con', 'member:1');
        $this->store->cardConflicts = Bingo::ATTEMPTS - 1;

        $this->bingo->record(Activity::of('k1', 'member:1', 'booth.visited', $this->clock->now(), 'booth:'.substr((string) $card->layout[0], 1)));
        self::assertSame([0 => ['k1']], $this->bingo->card('con', 1)->ticks());
        self::assertSame(Bingo::ATTEMPTS, $this->store->cardSaves);

        $this->store->cardConflicts = Bingo::ATTEMPTS;
        $this->expectException(BingoConflict::class);
        $this->bingo->record(Activity::of('k2', 'member:1', 'booth.visited', $this->clock->now(), 'booth:'.substr((string) $card->layout[1], 1)));
    }

    public function testTheStoreChecksVersions(): void
    {
        $store = new InMemoryBingoStore();
        $grid = new Grid(3);
        $bingo = new Bingo($store, new CardDealer(Games::SECRET), $this->events, $this->clock);
        $bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid));

        try {
            $bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid));
            self::fail('Two games had the same identifier.');
        } catch (BingoConflict $bingoConflict) {
            self::assertSame('The game g is at version 1, not 0.', $bingoConflict->getMessage());
        }

        $stale = $store->game('g');
        self::assertNotNull($stale);
        $stale->nextSerial();
        $stale->nextSerial();

        try {
            $store->saveGame($stale);
            self::fail('A stale game was saved.');
        } catch (BingoConflict $bingoConflict) {
            self::assertSame('The game g is at version 1, not 2.', $bingoConflict->getMessage());
        }

        $card = new Card('g', 1, 'member:1', ['a']);
        $card->tick('a', 'k1');

        try {
            $store->saveCard($card);
            self::fail('A card not kept was saved.');
        } catch (BingoConflict $bingoConflict) {
            self::assertSame('The card g/1 is not stored.', $bingoConflict->getMessage());
        }

        $store->addCard(new Card('g', 1, 'member:1', ['a']), 'f');
        $store->saveCard($card);

        $stored = $store->card('g', 1);
        self::assertNotNull($stored);
        self::assertSame(2, $stored->version());
        $stored->tick('a', 'k2');
        self::assertSame([0 => ['k1']], $this->ticksOf($store, 'g', 1));
    }

    public function testOpenCardsAreBounded(): void
    {
        $store = new InMemoryBingoStore();
        $grid = new Grid(3);
        $bingo = new Bingo($store, new CardDealer(Games::SECRET), $this->events, $this->clock);

        foreach (['a', 'b', 'c'] as $game) {
            $bingo->create($game, Games::pool(12), $grid, WinPatterns::line($grid));
            $bingo->deal($game, 'member:1');
        }

        $bingo->close('b');

        self::assertSame(['a', 'c'], array_map(static fn (Card $card): string => $card->game, $store->openCardsOf('member:1', 5)));
        self::assertSame(['a'], array_map(static fn (Card $card): string => $card->game, $store->openCardsOf('member:1', 1)));
        self::assertSame([], $store->openCardsOf('member:2', 5));
    }

    public function testUnknownGamesAndCardsAreRefused(): void
    {
        foreach ([
            'No bingo game has the identifier nope.' => fn (): mixed => $this->bingo->game('nope'),
            'The bingo game nope has no card 3.' => fn (): mixed => $this->bingo->card('nope', 3),
        ] as $message => $call) {
            try {
                $call();
                self::fail('Something unknown was found.');
            } catch (BingoRefused $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    /**
     * @return array<int, list<string>>
     */
    private function ticksOf(InMemoryBingoStore $store, string $game, int $serial): array
    {
        return $store->card($game, $serial)?->ticks() ?? [];
    }

    private function entry(int $position, string $key, string $object, EntryKind $kind = EntryKind::Recorded): Entry
    {
        return new Entry($position, $kind, Activity::of($key, 'member:1', 'booth.visited', new DateTimeImmutable('2026-11-12 20:00:00 UTC'), $object), new DateTimeImmutable('2026-11-12 20:00:00 UTC'));
    }

    public function testAStoreKeepsCopies(): void
    {
        $store = new InMemoryBingoStore();
        $grid = new Grid(3);
        $game = new BingoGame('g', Games::pool(12), $grid, WinPatterns::line($grid), TickMode::Called);
        $store->addGame($game);
        $game->call('s1');
        self::assertSame([], $store->game('g')?->called());

        $store->saveGame($game);
        $game->call('s2');
        self::assertSame(['s1'], $store->game('g')?->called());

        $card = new Card('g', 1, 'member:1', ['a', 'b']);
        $store->addCard($card, 'f1');
        $card->tick('a', 'k1');
        self::assertSame([], $this->ticksOf($store, 'g', 1));

        $store->saveCard($card);
        $card->tick('b', 'k2');
        self::assertSame([0 => ['k1']], $this->ticksOf($store, 'g', 1));

        [$open] = $store->openCardsOf('member:1', 5);
        $open->tick('b', 'k3');
        self::assertSame([0 => ['k1']], $this->ticksOf($store, 'g', 1));
    }

    public function testAStoreRefusesAGameNeverAddedAndSaysWhichCardClashes(): void
    {
        $store = new InMemoryBingoStore();
        $game = new BingoGame('g', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)));
        $game->nextSerial();

        try {
            $store->saveGame($game);
            self::fail('A game never added was saved.');
        } catch (BingoConflict $bingoConflict) {
            self::assertSame('The game g is not stored.', $bingoConflict->getMessage());
        }

        $store->addGame(new BingoGame('g', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3))));
        $store->addCard(new Card('g', 1, 'member:1', ['a']), 'f1');

        try {
            $store->addCard(new Card('g', 1, 'member:2', ['b']), 'f2');
            self::fail('Two cards had the same serial.');
        } catch (BingoConflict $bingoConflict) {
            self::assertSame('The card g/1 is at version 1, not 0.', $bingoConflict->getMessage());
        }

        $stale = new Card('g', 1, 'member:1', ['a']);
        $stale->tick('a', 'k1');
        $stale->tick('a', 'k2');

        try {
            $store->saveCard($stale);
            self::fail('A stale card was saved.');
        } catch (BingoConflict $bingoConflict) {
            self::assertSame('The card g/1 is at version 1, not 2.', $bingoConflict->getMessage());
        }
    }

    public function testADealtCardIsReadOnce(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid));

        $this->bingo->deal('g', 'member:1');

        self::assertSame(1, $this->store->cardOfReads);
    }

    public function testAGestureTicksEveryCardItTriggersOnAndEverySquareOfACard(): void
    {
        $grid = new Grid(3);
        $first = new SquarePool(array_map(static fn (int $n): Square => new Square('s'.$n, 'Square '.$n, new Trigger('talk.attended')), range(1, 8)));
        $this->bingo->create('a', $first, $grid, WinPatterns::line($grid));
        $this->bingo->create('b', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->bingo->create('c', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->bingo->deal('a', 'member:1');

        $cardB = $this->bingo->deal('b', 'member:1');
        $this->bingo->deal('c', 'member:1');

        $this->bingo->record(Activity::of('k1', 'member:1', 'booth.visited', new DateTimeImmutable('2026-11-12 20:00:00 UTC'), 'booth:'.substr((string) $cardB->layout[0], 1)));

        self::assertSame([0], array_keys($this->bingo->card('b', 1)->ticks()));

        $this->bingo->record(Activity::of('k2', 'member:1', 'talk.attended', new DateTimeImmutable('2026-11-12 20:00:00 UTC')));

        self::assertCount(8, array_filter($this->events->events, static fn (object $event): bool => $event instanceof SquareTicked && 'a' === $event->game));
    }

    public function testACancelledGestureThatTickedNothingReadsNoCard(): void
    {
        $grid = new Grid(3);
        $this->bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid));
        $card = $this->bingo->deal('g', 'member:1');
        $this->bingo->record(Activity::of('k1', 'member:1', 'booth.visited', new DateTimeImmutable('2026-11-12 20:00:00 UTC'), 'booth:'.substr((string) $card->layout[0], 1)));
        self::assertNotSame([], $this->bingo->card('g', 1)->ticks());
        $this->store->cardReads = 0;

        $this->bingo->cancel(Activity::of('k9', 'member:1', 'booth.visited', new DateTimeImmutable('2026-11-12 20:00:00 UTC'), 'booth:1'));

        self::assertSame(0, $this->store->cardReads);
    }

    public function testTheTicksOfACardAreReadByTheApplication(): void
    {
        $grid = new Grid(3);
        $game = $this->bingo->create('g', Games::pool(12), $grid, WinPatterns::line($grid), TickMode::Called);
        $card = $this->bingo->deal('g', 'member:1');
        $this->bingo->call('g', (string) $card->layout[0]);

        self::assertSame([4 => 4, 0 => 0], $this->bingo->ticked($this->bingo->game('g'), $card));
        self::assertSame([4 => 4], $this->bingo->ticked($game, $card));
    }
}
