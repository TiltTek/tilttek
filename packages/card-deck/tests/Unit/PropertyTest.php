<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\DeckState;
use Tilttek\CardDeck\Exception\IllegalGesture;
use Tilttek\CardDeck\Gesture\Collect;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Discard;
use Tilttek\CardDeck\Gesture\Draw;
use Tilttek\CardDeck\Gesture\End;
use Tilttek\CardDeck\Gesture\Flip;
use Tilttek\CardDeck\Gesture\Gesture;
use Tilttek\CardDeck\Gesture\GestureCodec;
use Tilttek\CardDeck\Gesture\Move;
use Tilttek\CardDeck\Gesture\Pick;
use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\CardDeck\Gesture\TurnFace;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\CardDeck\View\ZoneView;
use Tilttek\CardDeck\ZoneId;
use Tilttek\CardDeck\ZoneKind;
use Tilttek\Dice\Fairness\Seed;

/**
 * Properties over random games drawn from fixed seeds: whatever the gestures, no card is lost or duplicated, a refused
 * gesture changes nothing, no view holds a hidden card, and the journal replays to the same state.
 */
#[CoversClass(DeckState::class)]
final class PropertyTest extends TestCase
{
    private const array PLAYERS = ['p1', 'p2', 'p3'];

    /**
     * @return iterable<string, array{int}>
     */
    public static function seeds(): iterable
    {
        foreach ([1, 7, 42, 1999, 31337, 65537] as $seed) {
            yield 'seed '.$seed => [$seed];
        }
    }

    #[DataProvider('seeds')]
    public function testNoCardIsLostDuplicatedOrShownWhenHidden(int $random): void
    {
        $chance = new Randomizer(new Mt19937($random));
        $deck = StandardDeck::create(2);
        $seed = Seed::fromHex(bin2hex($chance->getBytes(32)));
        $expected = array_map(static fn (Card $card): string => $card->id->value, $deck->cards());
        sort($expected);
        $state = DeckState::start($deck, $seed, 'property');
        $journal = [];

        for ($step = 0; $step < 150; ++$step) {
            $gesture = $this->gesture($chance, $state);
            $before = $state->fingerprint();

            try {
                $next = $state->apply($gesture);
            } catch (IllegalGesture) {
                self::assertSame($before, $state->fingerprint(), 'A refused gesture changes nothing.');

                continue;
            }

            $journal[] = GestureCodec::fromArray(json_decode(json_encode($gesture->toArray(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
            $state = $next;
            $this->assertEveryCardOnce($state, $expected);
            $this->assertNoViewShowsAHiddenCard($state);
        }

        self::assertSame($state->fingerprint(), DeckState::replay($deck, $seed, 'property', $journal)->fingerprint());
    }

    /**
     * @param list<string> $expected
     */
    private function assertEveryCardOnce(DeckState $state, array $expected): void
    {
        $seen = [];

        foreach ($state->zones() as $zone) {
            foreach ($state->cardsIn($zone) as $card) {
                $seen[] = $card->value;
                self::assertTrue($state->zoneOf($card)->equals($zone));
            }
        }

        sort($seen);
        self::assertSame($expected, $seen);
    }

    private function assertNoViewShowsAHiddenCard(DeckState $state): void
    {
        foreach ([...array_map(static fn (string $player): PlayerId => new PlayerId($player), self::PLAYERS), null] as $viewer) {
            $hidden = [];

            foreach ($state->zones() as $zone) {
                foreach ($state->cardsIn($zone) as $card) {
                    if (ZoneKind::DrawPile === $zone->kind || (!$state->isFaceUp($card) && !$zone->isHandOf($viewer))) {
                        $hidden[] = $card->value;
                    }
                }
            }

            $views = $state->seenBy($viewer);
            $json = json_encode(array_map(static fn (ZoneView $zone): array => $zone->toArray(), $views), \JSON_THROW_ON_ERROR);

            foreach ($hidden as $card) {
                self::assertStringNotContainsString('"'.$card.'"', $json, \sprintf('The view of %s shows the hidden card %s.', $viewer->value ?? 'a spectator', $card));
            }

            foreach ($views as $view) {
                self::assertSame($state->count($view->zone), $view->count);
                self::assertCount(ZoneKind::DrawPile === $view->zone->kind ? 0 : $view->count, $view->cards);
            }
        }
    }

    private function gesture(Randomizer $chance, DeckState $state): Gesture
    {
        $player = new PlayerId(self::PLAYERS[$chance->getInt(0, 2)]);
        $zones = [...$state->zones(), ZoneId::pile('a'), ZoneId::pile('b'), ZoneId::hand($player)];
        $zone = $zones[$chance->getInt(0, \count($zones) - 1)];
        $other = $zones[$chance->getInt(0, \count($zones) - 1)];
        $card = new CardId(\sprintf('card-%02d', $chance->getInt(1, 54)));
        $faceUp = 1 === $chance->getInt(0, 1);
        $end = $faceUp ? End::Top : End::Bottom;

        return match ($chance->getInt(0, 7)) {
            0 => new Shuffle($zone),
            1 => new Draw($player, [Pick::Top, Pick::Bottom, Pick::Random][$chance->getInt(0, 2)], $zone, $faceUp),
            2 => new Deal(array_map(static fn (string $id): PlayerId => new PlayerId($id), \array_slice(self::PLAYERS, 0, $chance->getInt(1, 3))), $chance->getInt(1, 6), $faceUp, $zone),
            3 => new Discard($card),
            4 => new Flip($card),
            5 => new TurnFace($card, $chance->getInt(0, 1)),
            6 => new Move($card, $zone, $end, $faceUp),
            default => $zone->equals($other) ? new Shuffle($zone) : new Collect($zone, $other, $end, $faceUp),
        };
    }
}
