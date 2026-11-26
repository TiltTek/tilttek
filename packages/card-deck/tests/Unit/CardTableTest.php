<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\CardTable;
use Tilttek\CardDeck\DeckState;
use Tilttek\CardDeck\Exception\ConcurrentChange;
use Tilttek\CardDeck\Exception\IllegalGesture;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\Exception\UnknownGame;
use Tilttek\CardDeck\GameId;
use Tilttek\CardDeck\GameRecord;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Discard;
use Tilttek\CardDeck\Gesture\Draw;
use Tilttek\CardDeck\Gesture\Pick;
use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\Reveal;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\CardDeck\Store\InMemoryGameStore;
use Tilttek\CardDeck\View\PlayerView;
use Tilttek\CardDeck\ZoneId;
use Tilttek\Dice\Fairness\Commitment;
use Tilttek\Dice\Fairness\Seed;

#[CoversClass(CardTable::class)]
#[CoversClass(GameRecord::class)]
#[CoversClass(InMemoryGameStore::class)]
#[CoversClass(ConcurrentChange::class)]
#[CoversClass(UnknownGame::class)]
final class CardTableTest extends TestCase
{
    private InMemoryGameStore $store;

    private CardTable $table;

    private GameId $game;

    private Seed $seed;

    protected function setUp(): void
    {
        $this->store = new InMemoryGameStore();
        $this->table = new CardTable($this->store);
        $this->game = new GameId('g1');
        $this->seed = Seed::fromHex(str_repeat('cd', 32));
    }

    public function testAGameIsOpenedStartedPlayedAndFinished(): void
    {
        $commitment = $this->table->open($this->game, StandardDeck::create(), $this->seed);
        self::assertSame($this->seed->commitment(0)->hex, $commitment->hex);
        self::assertSame(0, $this->store->find($this->game)?->version);
        self::assertNull($this->store->find($this->game)->clientSeed);

        $started = $this->table->start($this->game, 'chosen');
        self::assertSame(DeckState::start(StandardDeck::create(), $this->seed, 'chosen')->fingerprint(), $started->fingerprint());
        self::assertSame('chosen', $this->store->find($this->game)?->clientSeed);

        $p1 = new PlayerId('p1');
        $after = $this->table->play($this->game, 1, new Deal([$p1, new PlayerId('p2')], 5));
        self::assertSame(5, $after->count(ZoneId::hand($p1)));
        $after = $this->table->play($this->game, 2, new Draw($p1, Pick::Random));

        $view = $this->table->view($this->game, $p1);
        self::assertSame($p1, $view->viewer);
        self::assertSame($commitment->hex, $view->commitment);
        self::assertSame(3, $view->version);
        self::assertFalse($view->finished);
        self::assertSame(6, $view->zone(ZoneId::hand($p1))?->count);
        self::assertEquals($after->seenBy($p1), $view->zones);
        self::assertNull($this->table->view($this->game)->viewer);

        $reveal = $this->table->finish($this->game);
        self::assertTrue($reveal->matches($commitment));
        self::assertSame('chosen', $reveal->clientSeed);
        self::assertSame($this->seed->hex(), $reveal->seed->hex());
        self::assertSame($after->fingerprint(), $reveal->replay()->fingerprint());
        self::assertCount(2, $reveal->gestures);
        self::assertTrue($this->table->view($this->game)->finished);
        self::assertSame(4, $this->table->view($this->game)->version);
    }

    public function testAGameIsOpenedOnce(): void
    {
        $this->table->open($this->game, StandardDeck::create(), $this->seed);

        $this->expectExceptionObject(ConcurrentChange::because('The game "g1" changed since version -1 was read.'));
        $this->table->open($this->game, StandardDeck::create(), $this->seed);
    }

    public function testAGameIsStartedOnce(): void
    {
        $this->table->open($this->game, StandardDeck::create(), $this->seed);
        $this->table->start($this->game);
        self::assertSame('', $this->store->find($this->game)?->clientSeed);

        $this->expectExceptionObject(IllegalGesture::because('The game is already started.'));
        $this->table->start($this->game, 'again');
    }

    public function testATooLongClientSeedStartsNothing(): void
    {
        $this->table->open($this->game, StandardDeck::create(), $this->seed);

        try {
            $this->table->start($this->game, str_repeat('x', 257));
            self::fail('A long client seed was accepted.');
        } catch (InvalidDefinition) {
            self::assertNull($this->store->find($this->game)?->clientSeed);
        }
    }

    public function testAGestureIsPlayedOnTheLastVersionOnly(): void
    {
        $this->table->open($this->game, StandardDeck::create(), $this->seed);
        $this->table->start($this->game);
        $this->table->play($this->game, 1, new Shuffle());

        try {
            $this->table->play($this->game, 1, new Shuffle());
            self::fail('A stale version was accepted.');
        } catch (ConcurrentChange $concurrentChange) {
            self::assertSame('The game "g1" is at version 2, not 1.', $concurrentChange->getMessage());
        }

        self::assertCount(1, $this->store->find($this->game)->gestures ?? []);
    }

    public function testARefusedGestureIsNotRecorded(): void
    {
        $this->table->open($this->game, StandardDeck::create(), $this->seed);
        $this->table->start($this->game);

        try {
            $this->table->play($this->game, 1, new Draw(new PlayerId('p1'), zone: ZoneId::discard()));
            self::fail('A draw from an empty zone was accepted.');
        } catch (IllegalGesture) {
            self::assertSame(1, $this->store->find($this->game)?->version);
        }
    }

    public function testAGameIsPlayedOnlyWhenStartedAndNotFinished(): void
    {
        $this->table->open($this->game, StandardDeck::create(), $this->seed);

        foreach ([
            fn (): DeckState => $this->table->play($this->game, 0, new Shuffle()),
            fn (): PlayerView => $this->table->view($this->game),
            fn (): Reveal => $this->table->finish($this->game),
        ] as $call) {
            try {
                $call();
                self::fail('A game not started was played.');
            } catch (IllegalGesture $illegal) {
                self::assertSame('The game is not started.', $illegal->getMessage());
            }
        }

        $this->table->start($this->game);
        $this->table->finish($this->game);

        foreach ([
            fn (): DeckState => $this->table->play($this->game, 2, new Shuffle()),
            fn (): Reveal => $this->table->finish($this->game),
        ] as $call) {
            try {
                $call();
                self::fail('A finished game was played.');
            } catch (IllegalGesture $illegal) {
                self::assertSame('The game is finished.', $illegal->getMessage());
            }
        }
    }

    public function testAGameRecordsAtMostItsBoundOfGestures(): void
    {
        $record = new GameRecord($this->game, StandardDeck::create(), $this->seed, 1, '', array_fill(0, GameRecord::MAX_GESTURES - 1, new Discard(new CardId('card-01'))));
        $this->store->save(new GameRecord($this->game, StandardDeck::create(), $this->seed));
        $this->store->save($record);

        $this->table->play($this->game, 1, new Discard(new CardId('card-02')));

        $this->expectExceptionObject(IllegalGesture::because('A game records at most 10000 gestures.'));
        $this->table->play($this->game, 2, new Discard(new CardId('card-03')));
    }

    public function testAnUnknownGameIsRefused(): void
    {
        $this->expectExceptionObject(UnknownGame::because('The store holds no game "g1".'));

        $this->table->view($this->game);
    }

    public function testTheStoreWritesOverThePreviousVersionOnly(): void
    {
        $record = new GameRecord($this->game, StandardDeck::create(), $this->seed);
        $this->store->save($record);
        $this->store->save($record->withStart('s'));
        self::assertSame(1, $this->store->find($this->game)?->version);
        self::assertNull($this->store->find(new GameId('other')));

        $this->expectExceptionObject(ConcurrentChange::because('The game "g1" changed since version 0 was read.'));
        $this->store->save($record->withStart('t'));
    }

    public function testARecordChangesOneVersionAtATime(): void
    {
        $record = new GameRecord($this->game, StandardDeck::create(), $this->seed);
        $started = $record->withStart('s');
        $played = $started->withGesture(new Shuffle());
        $finished = $played->withFinish();

        self::assertSame([0, 1, 2, 3], [$record->version, $started->version, $played->version, $finished->version]);
        self::assertSame(['s', 's', 's'], [$started->clientSeed, $played->clientSeed, $finished->clientSeed]);
        self::assertEquals([new Shuffle()], $finished->gestures);
        self::assertSame([false, false, false, true], [$record->finished, $started->finished, $played->finished, $finished->finished]);
        self::assertTrue($finished->withGesture(new Shuffle())->finished);
        self::assertTrue($finished->withStart('x')->finished);
        self::assertEquals([new Shuffle()], $finished->withStart('x')->gestures);
        self::assertSame('s', $finished->withGesture(new Shuffle())->clientSeed);
        self::assertInstanceOf(Commitment::class, $record->commitment());
        self::assertSame($played->state()->fingerprint(), DeckState::replay(StandardDeck::create(), $this->seed, 's', [new Shuffle()])->fingerprint());

        $this->expectExceptionObject(IllegalGesture::because('The game is not started.'));
        $record->state();
    }

    public function testAPublishedGameIsVerified(): void
    {
        $this->table->open($this->game, StandardDeck::create(), $this->seed);
        $this->table->start($this->game, 'c');

        $state = $this->table->play($this->game, 1, new Draw(new PlayerId('p1'), Pick::Random));
        $reveal = Reveal::fromArray(json_decode(json_encode($this->table->finish($this->game)->toArray(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));

        self::assertTrue($reveal->matches($this->seed->commitment()));
        self::assertFalse($reveal->matches(Seed::fromHex(str_repeat('ef', 32))->commitment()));
        self::assertSame($state->fingerprint(), $reveal->replay()->fingerprint());
    }
}
