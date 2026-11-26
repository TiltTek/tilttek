<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\DeckState;
use Tilttek\CardDeck\Exception\IllegalGesture;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\Face;
use Tilttek\CardDeck\Gesture\Collect;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Discard;
use Tilttek\CardDeck\Gesture\Draw;
use Tilttek\CardDeck\Gesture\End;
use Tilttek\CardDeck\Gesture\Flip;
use Tilttek\CardDeck\Gesture\Gesture;
use Tilttek\CardDeck\Gesture\Move;
use Tilttek\CardDeck\Gesture\Pick;
use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\CardDeck\Gesture\TurnFace;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\Shuffler;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\CardDeck\ZoneId;
use Tilttek\Dice\Fairness\Seed;

#[CoversClass(DeckState::class)]
#[CoversClass(IllegalGesture::class)]
final class DeckStateTest extends TestCase
{
    private Seed $seed;

    private PlayerId $p1;

    private PlayerId $p2;

    protected function setUp(): void
    {
        $this->seed = Seed::fromHex(str_repeat('ab', 32));
        $this->p1 = new PlayerId('p1');
        $this->p2 = new PlayerId('p2');
    }

    public function testAGameStartsWithTheDeckShuffledFromNumberZero(): void
    {
        $deck = StandardDeck::create();
        $state = DeckState::start($deck, $this->seed, 'players');
        $ids = array_map(static fn (Card $card): string => $card->id->value, $deck->cards());

        self::assertSame(['card-45', 'card-25', 'card-05', 'card-10', 'card-46', 'card-07', 'card-18', 'card-17'], \array_slice($this->ids($state, ZoneId::drawPile()), 0, 8));
        self::assertSame(Shuffler::shuffle($ids, $this->seed->randomizer('players', 0)), $this->ids($state, ZoneId::drawPile()));
        self::assertSame(['draw', 'discard', 'table'], $this->zones($state));
        self::assertTrue($state->isEmpty(ZoneId::discard()));
        self::assertSame(52, $state->count(ZoneId::drawPile()));
        self::assertSame(0, $state->count(ZoneId::pile('none')));
        self::assertSame([], $state->cardsIn(ZoneId::pile('none')));
        self::assertFalse($state->isFaceUp(new CardId('card-01')));
        self::assertSame('AS', $state->faceOf(new CardId('card-01'))->key);
        self::assertSame('draw', $state->zoneOf(new CardId('card-01'))->toString());
        self::assertSame($deck, $state->deck());
        self::assertSame('7374aba6d36c921100251ba46df475eb2f661971f97d96e7470bd384596f8d94', $state->fingerprint());
        self::assertNotSame($this->ids($state, ZoneId::drawPile()), $this->ids(DeckState::start($deck, $this->seed, 'others'), ZoneId::drawPile()));
        self::assertSame($this->ids(DeckState::start($deck, $this->seed), ZoneId::drawPile()), $this->ids(DeckState::start($deck, $this->seed, ''), ZoneId::drawPile()));
    }

    public function testTheClientSeedIsBounded(): void
    {
        self::assertSame(52, DeckState::start(StandardDeck::create(), $this->seed, str_repeat('x', 256))->count(ZoneId::drawPile()));

        $this->expectExceptionObject(InvalidDefinition::because('A client seed is at most 256 bytes.'));
        DeckState::start(StandardDeck::create(), $this->seed, str_repeat('x', 257));
    }

    public function testAPlayerDrawsFromTheTopTheBottomOrAtRandom(): void
    {
        $state = $this->start();
        $draw = $this->ids($state, ZoneId::drawPile());

        $after = $state->apply(new Draw($this->p1));
        self::assertSame([$draw[0]], $this->ids($after, ZoneId::hand($this->p1)));
        self::assertFalse($after->isFaceUp(new CardId($draw[0])));
        self::assertSame(\array_slice($draw, 1), $this->ids($after, ZoneId::drawPile()));
        self::assertSame($draw, $this->ids($state, ZoneId::drawPile()), 'The state applied to is left as it was.');
        self::assertSame(['draw', 'discard', 'table', 'hand:p1'], $this->zones($after));

        $after = $after->apply(new Draw($this->p1, Pick::Bottom, faceUp: true));
        self::assertSame([$draw[0], $draw[51]], $this->ids($after, ZoneId::hand($this->p1)));
        self::assertTrue($after->isFaceUp(new CardId($draw[51])));

        $random = $draw[1 + $this->seed->randomizer('', 1)->getInt(0, 49)];
        $after = $after->apply(new Draw($this->p2, Pick::Random));
        self::assertSame([$random], $this->ids($after, ZoneId::hand($this->p2)));

        $fromHand = $after->apply(new Draw($this->p2, Pick::Top, ZoneId::hand($this->p1)));
        self::assertSame([$random, $draw[0]], $this->ids($fromHand, ZoneId::hand($this->p2)));
        self::assertSame([$draw[51]], $this->ids($fromHand, ZoneId::hand($this->p1)));
    }

    public function testARandomDrawUsesTheNextNumberOfTheSeed(): void
    {
        $state = $this->start();
        $draw = $this->ids($state, ZoneId::drawPile());
        $shuffled = Shuffler::shuffle($draw, $this->seed->randomizer('', 1));
        $after = $state->apply(new Shuffle())->apply(new Draw($this->p1, Pick::Random));

        self::assertSame($shuffled[$this->seed->randomizer('', 2)->getInt(0, 51)], $this->ids($after, ZoneId::hand($this->p1))[0]);
    }

    public function testNothingIsDrawnFromAnEmptyOrUnknownZone(): void
    {
        $state = $this->start();

        try {
            $state->apply(new Draw($this->p1, Pick::Top, ZoneId::discard()));
            self::fail('A card was drawn from an empty zone.');
        } catch (IllegalGesture $illegalGesture) {
            self::assertSame('The zone "discard" is empty.', $illegalGesture->getMessage());
        }

        $this->expectExceptionObject(IllegalGesture::because('The zone "pile:none" is not open in this game.'));
        $state->apply(new Draw($this->p1, Pick::Top, ZoneId::pile('none')));
    }

    public function testAnEmptyDrawPileIsNotADrawPileOfJokers(): void
    {
        $state = DeckState::start(StandardDeck::create(2), $this->seed);

        foreach ($state->cardsIn(ZoneId::drawPile()) as $card) {
            if ('JK' !== $state->faceOf($card)->key) {
                $state = $state->apply(new Discard($card));
            }
        }

        self::assertFalse($state->isEmpty(ZoneId::drawPile()));
        self::assertSame(2, $state->count(ZoneId::drawPile()));

        $state = $state->apply(new Draw($this->p1))->apply(new Draw($this->p1));
        self::assertSame(['JK', 'JK'], array_map(static fn (CardId $card): string => $state->faceOf($card)->key, $state->cardsIn(ZoneId::hand($this->p1))));
        self::assertTrue($state->isEmpty(ZoneId::drawPile()));

        $this->expectExceptionObject(IllegalGesture::because('The zone "draw" is empty.'));
        $state->apply(new Draw($this->p1));
    }

    public function testCardsAreDealtAroundThePlayers(): void
    {
        $state = $this->start();
        $draw = $this->ids($state, ZoneId::drawPile());
        $after = $state->apply(new Deal([$this->p2, $this->p1], 2));

        self::assertSame([$draw[0], $draw[2]], $this->ids($after, ZoneId::hand($this->p2)));
        self::assertSame([$draw[1], $draw[3]], $this->ids($after, ZoneId::hand($this->p1)));
        self::assertSame(['draw', 'discard', 'table', 'hand:p2', 'hand:p1'], $this->zones($after));
        self::assertFalse($after->isFaceUp(new CardId($draw[0])));
        self::assertTrue($state->apply(new Deal([$this->p1], 1, true))->isFaceUp(new CardId($draw[0])));

        $all = $state->apply(new Deal([$this->p1, $this->p2], 26));
        self::assertTrue($all->isEmpty(ZoneId::drawPile()));
        self::assertSame(26, $all->count(ZoneId::hand($this->p2)));

        $this->expectExceptionObject(IllegalGesture::because('The zone "draw" holds fewer than the 54 cards of the deal.'));
        $state->apply(new Deal([$this->p1, $this->p2], 27));
    }

    public function testADealFromAnotherZone(): void
    {
        $state = $this->start()->apply(new Collect(ZoneId::drawPile(), ZoneId::pile('stock')));
        $stock = $this->ids($state, ZoneId::pile('stock'));

        self::assertSame([$stock[0]], $this->ids($state->apply(new Deal([$this->p1], 1, from: ZoneId::pile('stock'))), ZoneId::hand($this->p1)));

        $this->expectExceptionObject(IllegalGesture::because('The zone "draw" holds fewer than the 1 cards of the deal.'));
        $state->apply(new Deal([$this->p1], 1));
    }

    public function testADiscardedCardLiesOnTopFaceUp(): void
    {
        $state = $this->start();
        $draw = $this->ids($state, ZoneId::drawPile());
        $after = $state->apply(new Discard(new CardId($draw[5])))->apply(new Discard(new CardId($draw[9])));

        self::assertSame([$draw[9], $draw[5]], $this->ids($after, ZoneId::discard()));
        self::assertTrue($after->isFaceUp(new CardId($draw[5])));
        self::assertSame(50, $after->count(ZoneId::drawPile()));
        self::assertNotContains($draw[5], $this->ids($after, ZoneId::drawPile()));
    }

    public function testACardIsFlippedWhereItLiesExceptInTheDrawPile(): void
    {
        $state = $this->start()->apply(new Move(new CardId('card-01'), ZoneId::table()));

        $up = $state->apply(new Flip(new CardId('card-01')));
        self::assertTrue($up->isFaceUp(new CardId('card-01')));
        self::assertFalse($up->apply(new Flip(new CardId('card-01')))->isFaceUp(new CardId('card-01')));

        $this->expectExceptionObject(IllegalGesture::because('The cards of the draw pile always lie face down.'));
        $state->apply(new Flip(new CardId('card-02')));
    }

    public function testACardOfSeveralFacesTurnsToAnother(): void
    {
        $moon = new CardId('moon');
        $state = DeckState::start(new Deck(new Card($moon, [new Face('new'), new Face('full')], new Face('sky')), Card::of('sun', 'sun')), $this->seed);

        $full = $state->apply(new TurnFace($moon, 1));
        self::assertSame('full', $full->faceOf($moon)->key);
        self::assertFalse($full->isFaceUp($moon));
        self::assertSame('full', $full->apply(new Move($moon, ZoneId::table(), faceUp: true))->faceOf($moon)->key);
        self::assertSame('new', $full->apply(new TurnFace($moon, 0))->faceOf($moon)->key);
        self::assertNotSame($state->fingerprint(), $full->fingerprint());

        $this->expectExceptionObject(IllegalGesture::because('The card "moon" has no face 2.'));
        $state->apply(new TurnFace($moon, 2));
    }

    public function testACardMovesToTheTopOrTheBottomOfAZone(): void
    {
        $state = $this->start();
        $draw = $this->ids($state, ZoneId::drawPile());
        $a = new CardId($draw[10]);
        $b = new CardId($draw[20]);
        $after = $state->apply(new Move($a, ZoneId::pile('market'), faceUp: true))->apply(new Move($b, ZoneId::pile('market')));

        self::assertSame([$draw[20], $draw[10]], $this->ids($after, ZoneId::pile('market')));
        self::assertTrue($after->isFaceUp($a));
        self::assertFalse($after->isFaceUp($b));
        self::assertSame('pile:market', $after->zoneOf($a)->toString());

        $bottom = $after->apply(new Move($b, ZoneId::pile('market'), End::Bottom));
        self::assertSame([$draw[10], $draw[20]], $this->ids($bottom, ZoneId::pile('market')));

        $back = $after->apply(new Move($a, ZoneId::drawPile(), End::Bottom, true));
        self::assertSame($draw[10], $this->ids($back, ZoneId::drawPile())[50]);
        self::assertFalse($back->isFaceUp($a), 'A card in the draw pile lies face down.');

        $this->expectExceptionObject(IllegalGesture::because('The deck holds no card "ghost".'));
        $state->apply(new Move(new CardId('ghost'), ZoneId::table()));
    }

    public function testAZoneIsCollectedInItsOrder(): void
    {
        $state = $this->start();
        $draw = $this->ids($state, ZoneId::drawPile());
        $state = $state->apply(new Discard(new CardId($draw[0])))->apply(new Discard(new CardId($draw[1])))->apply(new Move(new CardId($draw[2]), ZoneId::table()));
        $rest = \array_slice($draw, 3);

        $onTop = $state->apply(new Collect(ZoneId::discard(), ZoneId::drawPile()));
        self::assertSame([$draw[1], $draw[0], ...$rest], $this->ids($onTop, ZoneId::drawPile()));
        self::assertFalse($onTop->isFaceUp(new CardId($draw[1])));
        self::assertTrue($onTop->isEmpty(ZoneId::discard()));
        self::assertSame(['draw', 'discard', 'table'], $this->zones($onTop));

        $under = $state->apply(new Collect(ZoneId::discard(), ZoneId::drawPile(), End::Bottom, true));
        self::assertSame([...$rest, $draw[1], $draw[0]], $this->ids($under, ZoneId::drawPile()));

        $toTable = $state->apply(new Collect(ZoneId::discard(), ZoneId::table(), End::Bottom, true));
        self::assertSame([$draw[2], $draw[1], $draw[0]], $this->ids($toTable, ZoneId::table()));
        self::assertTrue($toTable->isFaceUp(new CardId($draw[0])));
        self::assertSame('table', $toTable->zoneOf(new CardId($draw[0]))->toString());

        $this->expectExceptionObject(IllegalGesture::because('The zone "pile:none" is not open in this game.'));
        $state->apply(new Collect(ZoneId::pile('none'), ZoneId::table()));
    }

    public function testAShuffleIsNotANewGame(): void
    {
        $state = $this->start();
        $draw = $this->ids($state, ZoneId::drawPile());
        $state = $state->apply(new Move(new CardId($draw[0]), ZoneId::table(), faceUp: true));

        $shuffled = $state->apply(new Shuffle());
        self::assertSame(Shuffler::shuffle(\array_slice($draw, 1), $this->seed->randomizer('', 1)), $this->ids($shuffled, ZoneId::drawPile()));
        self::assertSame([$draw[0]], $this->ids($shuffled, ZoneId::table()), 'The other zones stay as they are.');
        self::assertTrue($shuffled->isFaceUp(new CardId($draw[0])));

        $twice = $shuffled->apply(new Shuffle());
        self::assertSame(Shuffler::shuffle($this->ids($shuffled, ZoneId::drawPile()), $this->seed->randomizer('', 2)), $this->ids($twice, ZoneId::drawPile()));

        $table = $state->apply(new Move(new CardId($draw[1]), ZoneId::table(), faceUp: true))->apply(new Shuffle(ZoneId::table()));
        self::assertFalse($table->isFaceUp(new CardId($draw[0])), 'A shuffled zone lies face down.');
        self::assertFalse($table->isFaceUp(new CardId($draw[1])));
        self::assertSame(Shuffler::shuffle([$draw[1], $draw[0]], $this->seed->randomizer('', 1)), $this->ids($table, ZoneId::table()));

        $this->expectExceptionObject(IllegalGesture::because('The zone "pile:none" is not open in this game.'));
        $state->apply(new Shuffle(ZoneId::pile('none')));
    }

    public function testTheFingerprintHoldsTheNextNumberOfTheSeed(): void
    {
        $state = $this->start()->apply(new Move(new CardId('card-01'), ZoneId::table()));
        $shuffled = $state->apply(new Shuffle(ZoneId::table()));

        self::assertSame($this->ids($state, ZoneId::table()), $this->ids($shuffled, ZoneId::table()));
        self::assertNotSame($state->fingerprint(), $shuffled->fingerprint());
        self::assertSame($state->fingerprint(), $this->start()->apply(new Move(new CardId('card-01'), ZoneId::table()))->fingerprint());
        self::assertNotSame($state->fingerprint(), $state->apply(new Flip(new CardId('card-01')))->fingerprint());
    }

    public function testAGameOpensAtMost256Zones(): void
    {
        $card = new CardId('only');
        $state = DeckState::start(new Deck(Card::of('only', 'X')), $this->seed);

        for ($index = 1; $index <= 253; ++$index) {
            $state = $state->apply(new Move($card, ZoneId::pile('p'.$index)));
        }

        self::assertCount(256, $state->zones());
        self::assertSame('pile:p1', $state->apply(new Move($card, ZoneId::pile('p1')))->zoneOf($card)->toString());

        $this->expectExceptionObject(IllegalGesture::because('A game opens at most 256 zones.'));
        $state->apply(new Move($card, ZoneId::pile('p254')));
    }

    public function testAReplayGivesTheSameState(): void
    {
        $gestures = [new Deal([$this->p1, $this->p2], 3), new Draw($this->p1, Pick::Random), new Shuffle(), new Discard(new CardId('card-07'))];
        $state = $this->start();

        foreach ($gestures as $gesture) {
            $state = $state->apply($gesture);
        }

        self::assertSame($state->fingerprint(), DeckState::replay(StandardDeck::create(), $this->seed, '', $gestures)->fingerprint());
        self::assertNotSame($state->fingerprint(), DeckState::replay(StandardDeck::create(), $this->seed, 'other', $gestures)->fingerprint());
    }

    public function testAnUnknownCardOrGestureIsRefused(): void
    {
        $state = $this->start();

        foreach ([
            static fn (): ZoneId => $state->zoneOf(new CardId('ghost')),
            static fn (): bool => $state->isFaceUp(new CardId('ghost')),
            static fn (): Face => $state->faceOf(new CardId('ghost')),
            static fn (): DeckState => $state->apply(new Flip(new CardId('ghost'))),
            static fn (): DeckState => $state->apply(new TurnFace(new CardId('ghost'), 0)),
            static fn (): DeckState => $state->apply(new Discard(new CardId('ghost'))),
        ] as $call) {
            try {
                $call();
                self::fail('An unknown card was reached.');
            } catch (IllegalGesture $illegal) {
                self::assertSame('The deck holds no card "ghost".', $illegal->getMessage());
            }
        }

        $gesture = new class implements Gesture {
            public function toArray(): array
            {
                return [];
            }
        };

        $this->expectExceptionObject(IllegalGesture::because(\sprintf('The gesture %s is not one the library knows.', $gesture::class)));
        $state->apply($gesture);
    }

    private function start(): DeckState
    {
        return DeckState::start(StandardDeck::create(), $this->seed);
    }

    /**
     * @return list<string>
     */
    private function ids(DeckState $state, ZoneId $zone): array
    {
        return array_map(static fn (CardId $card): string => $card->value, $state->cardsIn($zone));
    }

    /**
     * @return list<string>
     */
    private function zones(DeckState $state): array
    {
        return array_map(static fn (ZoneId $zone): string => $zone->toString(), $state->zones());
    }
}
