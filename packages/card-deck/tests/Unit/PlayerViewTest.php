<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\DeckState;
use Tilttek\CardDeck\Face;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Draw;
use Tilttek\CardDeck\Gesture\Move;
use Tilttek\CardDeck\Gesture\TurnFace;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\CardDeck\View\PlayerView;
use Tilttek\CardDeck\View\SeenCard;
use Tilttek\CardDeck\View\ZoneView;
use Tilttek\CardDeck\ZoneId;
use Tilttek\Dice\Fairness\Seed;

#[CoversClass(PlayerView::class)]
#[CoversClass(ZoneView::class)]
#[CoversClass(SeenCard::class)]
#[CoversClass(DeckState::class)]
final class PlayerViewTest extends TestCase
{
    public function testAPlayerSeesTheirHandTheCardsFaceUpAndTheNumberOfTheOthers(): void
    {
        $p1 = new PlayerId('p1');
        $p2 = new PlayerId('p2');
        $state = DeckState::start(StandardDeck::create(0, 'blue'), Seed::fromHex(str_repeat('ab', 32)));
        $draw = $state->cardsIn(ZoneId::drawPile());
        $state = $state->apply(new Deal([$p1, $p2], 1))->apply(new Draw($p2, faceUp: true))->apply(new Move($draw[3], ZoneId::table()));

        $mine = $state->seenBy($p1);
        self::assertSame(['draw', 'discard', 'table', 'hand:p1', 'hand:p2'], array_map(static fn (ZoneView $zone): string => $zone->zone->toString(), $mine));
        self::assertSame(['zone' => 'draw', 'count' => 48, 'cards' => []], $mine[0]->toArray());
        self::assertSame(['zone' => 'discard', 'count' => 0, 'cards' => []], $mine[1]->toArray());
        self::assertSame(['zone' => 'table', 'count' => 1, 'cards' => [['card' => null, 'face' => null, 'back' => 'blue', 'face_up' => false]]], $mine[2]->toArray());
        self::assertSame([['card' => $draw[0]->value, 'face' => $state->faceOf($draw[0])->key, 'back' => 'blue', 'face_up' => false]], $mine[3]->toArray()['cards']);
        self::assertSame([
            ['card' => null, 'face' => null, 'back' => 'blue', 'face_up' => false],
            ['card' => $draw[2]->value, 'face' => $state->faceOf($draw[2])->key, 'back' => 'blue', 'face_up' => true],
        ], $mine[4]->toArray()['cards']);

        $theirs = $state->seenBy($p2);
        self::assertTrue($theirs[3]->cards[0]->isHidden());
        self::assertFalse($theirs[4]->cards[0]->isHidden());
        self::assertSame($draw[1]->value, $theirs[4]->cards[0]->id?->value);

        $spectator = $state->seenBy(null);
        self::assertTrue($spectator[4]->cards[0]->isHidden());
        self::assertFalse($spectator[4]->cards[1]->isHidden());
    }

    public function testTheViewShowsTheFaceTurned(): void
    {
        $moon = new CardId('moon');
        $state = DeckState::start(new Deck(new Card($moon, [new Face('new'), new Face('full')], new Face('sky'))), Seed::fromHex(str_repeat('ab', 32)));
        $state = $state->apply(new TurnFace($moon, 1))->apply(new Move($moon, ZoneId::table(), faceUp: true));

        self::assertSame('full', $state->seenBy(null)[2]->cards[0]->face?->key);
    }

    public function testAPlayerViewFindsItsZonesAndGoesToAnArray(): void
    {
        $zone = new ZoneView(ZoneId::discard(), 1, [SeenCard::visible(new CardId('c'), new Face('AS'), new Face('b'), true)]);
        $view = new PlayerView(new PlayerId('p1'), str_repeat('0', 64), 3, false, [new ZoneView(ZoneId::drawPile(), 5, []), $zone]);

        self::assertSame($zone, $view->zone(ZoneId::discard()));
        self::assertNull($view->zone(ZoneId::table()));
        self::assertSame([
            'viewer' => 'p1',
            'commitment' => str_repeat('0', 64),
            'version' => 3,
            'finished' => false,
            'zones' => [
                ['zone' => 'draw', 'count' => 5, 'cards' => []],
                ['zone' => 'discard', 'count' => 1, 'cards' => [['card' => 'c', 'face' => 'AS', 'back' => 'b', 'face_up' => true]]],
            ],
        ], $view->toArray());
        self::assertNull(new PlayerView(null, '', 0, true, [])->toArray()['viewer']);
        self::assertTrue(new PlayerView(null, '', 0, true, [])->toArray()['finished']);
    }
}
