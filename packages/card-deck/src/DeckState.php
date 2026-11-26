<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Random\Randomizer;
use Tilttek\CardDeck\Exception\IllegalGesture;
use Tilttek\CardDeck\Exception\InvalidDefinition;
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
use Tilttek\CardDeck\View\SeenCard;
use Tilttek\CardDeck\View\ZoneView;
use Tilttek\Dice\Fairness\Seed;

/**
 * The whole state of a game, as the server alone knows it: every card in its zone and its place, face up or down, the
 * face it shows, and the next number drawn from the seed. It is a value: `apply()` returns a new state and leaves this
 * one as it was, even when it refuses the gesture.
 *
 * Every card of the deck is in exactly one zone at every moment. A zone appears the first time a card lands in it and
 * stays, empty or not: an empty zone holds no card, which a zone holding only jokers never is.
 */
final class DeckState
{
    /** The most zones a game opens: hands and named piles included. */
    public const int MAX_ZONES = 256;

    /** @var array<string, list<string>> the card identifiers of each zone, top first, by zone */
    private array $zones = [];

    /** @var array<string, ZoneId> */
    private array $zoneIds = [];

    /** @var array<string, string> the zone of each card */
    private array $where = [];

    /** @var array<string, bool> */
    private array $faceUp = [];

    /** @var array<string, int> the face each card shows, numbered from 0 */
    private array $face = [];

    private int $nonce = 0;

    private function __construct(
        private readonly Deck $deck,
        private readonly Seed $seed,
        private readonly string $clientSeed,
    ) {
    }

    /**
     * A new game: every card face down in the draw pile, in the order of the deck, then shuffled from the number 0 of
     * the seed. A `Shuffle` later in the game draws the numbers after it.
     *
     * @param string $clientSeed chosen by the players after the commitment to the seed was published
     *
     * @throws InvalidDefinition when the client seed is longer than `Seed::MAX_CLIENT_SEED` bytes
     */
    public static function start(Deck $deck, Seed $seed, string $clientSeed = ''): self
    {
        if (\strlen($clientSeed) > Seed::MAX_CLIENT_SEED) {
            throw InvalidDefinition::because(\sprintf('A client seed is at most %d bytes.', Seed::MAX_CLIENT_SEED));
        }

        $state = new self($deck, $seed, $clientSeed);
        $ids = array_map(static fn (Card $card): string => $card->id->value, $deck->cards());
        $state->put($ids, ZoneId::drawPile(), End::Top, false);
        $state->put([], ZoneId::discard(), End::Top, false);
        $state->put([], ZoneId::table(), End::Top, false);
        $state->shuffle(ZoneId::drawPile());

        return $state;
    }

    /**
     * The state a journal leads to, gesture by gesture, from the start of the game.
     *
     * @param iterable<Gesture> $gestures
     *
     * @throws IllegalGesture    when a gesture of the journal cannot be applied
     * @throws InvalidDefinition when the client seed is too long
     */
    public static function replay(Deck $deck, Seed $seed, string $clientSeed, iterable $gestures): self
    {
        $state = self::start($deck, $seed, $clientSeed);

        foreach ($gestures as $gesture) {
            $state = $state->apply($gesture);
        }

        return $state;
    }

    /**
     * @throws IllegalGesture when the state refuses the gesture; this state is left as it was
     */
    public function apply(Gesture $gesture): self
    {
        $next = clone $this;

        match (true) {
            $gesture instanceof Shuffle => $next->shuffle($gesture->zone),
            $gesture instanceof Draw => $next->move($next->take($gesture->zone, $gesture->from), ZoneId::hand($gesture->player), End::Bottom, $gesture->faceUp),
            $gesture instanceof Deal => $next->deal($gesture),
            $gesture instanceof Discard => $next->move($next->card($gesture->card), ZoneId::discard(), End::Top, true),
            $gesture instanceof Flip => $next->flip($next->card($gesture->card)),
            $gesture instanceof TurnFace => $next->turnFace($next->card($gesture->card), $gesture->face),
            $gesture instanceof Move => $next->move($next->card($gesture->card), $gesture->to, $gesture->at, $gesture->faceUp),
            $gesture instanceof Collect => $next->collect($gesture),
            default => throw IllegalGesture::because(\sprintf('The gesture %s is not one the library knows.', $gesture::class)),
        };

        return $next;
    }

    public function deck(): Deck
    {
        return $this->deck;
    }

    /**
     * @return list<ZoneId> in the order they appeared
     */
    public function zones(): array
    {
        return array_values($this->zoneIds);
    }

    /**
     * The cards of a zone, top first — the whole truth, for the server only; a viewer gets `seenBy()`.
     *
     * @return list<CardId>
     */
    public function cardsIn(ZoneId $zone): array
    {
        return array_map(static fn (string $id): CardId => new CardId($id), $this->zones[$zone->toString()] ?? []);
    }

    public function count(ZoneId $zone): int
    {
        return \count($this->zones[$zone->toString()] ?? []);
    }

    /**
     * Whether the zone holds no card at all: a draw pile of jokers only is not empty.
     */
    public function isEmpty(ZoneId $zone): bool
    {
        return 0 === $this->count($zone);
    }

    /**
     * @throws IllegalGesture when the deck holds no such card
     */
    public function zoneOf(CardId $card): ZoneId
    {
        return $this->zoneIds[$this->where[$this->card($card)]];
    }

    /**
     * @throws IllegalGesture when the deck holds no such card
     */
    public function isFaceUp(CardId $card): bool
    {
        return $this->faceUp[$this->card($card)];
    }

    /**
     * The face the card shows when it lies face up.
     *
     * @throws IllegalGesture when the deck holds no such card
     */
    public function faceOf(CardId $card): Face
    {
        $id = $this->card($card);

        return $this->deck->card($card)->faces[$this->face[$id]];
    }

    /**
     * The SHA-256 of the places, sides and faces of every card and of the next number of the seed, as 64 hexadecimal
     * digits: two states with the same fingerprint are the same game. It holds neither the seed nor the client seed.
     */
    public function fingerprint(): string
    {
        $zones = [];

        foreach ($this->zones as $key => $ids) {
            $zones[$key] = array_map(fn (string $id): array => [$id, $this->faceUp[$id], $this->face[$id]], $ids);
        }

        return hash('sha256', json_encode(['zones' => $zones, 'nonce' => $this->nonce], \JSON_THROW_ON_ERROR));
    }

    /**
     * Every zone as a player sees it — a spectator with no player: a card is visible when it lies face up outside the
     * draw pile, or in the viewer's own hand; every other card shows its back alone, and the draw pile only its number
     * of cards.
     *
     * @return list<ZoneView>
     */
    public function seenBy(?PlayerId $viewer): array
    {
        $views = [];

        foreach ($this->zoneIds as $key => $zone) {
            $cards = [];

            if (ZoneKind::DrawPile !== $zone->kind) {
                foreach ($this->zones[$key] as $id) {
                    $card = $this->deck->card(new CardId($id));
                    $cards[] = $this->faceUp[$id] || $zone->isHandOf($viewer)
                        ? SeenCard::visible($card->id, $card->faces[$this->face[$id]], $card->back, $this->faceUp[$id])
                        : SeenCard::hidden($card->back);
                }
            }

            $views[] = new ZoneView($zone, \count($this->zones[$key]), $cards);
        }

        return $views;
    }

    private function shuffle(ZoneId $zone): void
    {
        $key = $this->known($zone);
        $this->zones[$key] = Shuffler::shuffle($this->zones[$key], $this->randomizer());

        foreach ($this->zones[$key] as $id) {
            $this->faceUp[$id] = false;
        }
    }

    private function deal(Deal $deal): void
    {
        $key = $this->known($deal->from);
        $needed = $deal->count * \count($deal->players);

        if (\count($this->zones[$key]) < $needed) {
            throw IllegalGesture::because(\sprintf('The zone "%s" holds fewer than the %d cards of the deal.', $key, $needed));
        }

        for ($round = 0; $round < $deal->count; ++$round) {
            foreach ($deal->players as $player) {
                $this->move($this->zones[$key][0], ZoneId::hand($player), End::Bottom, $deal->faceUp);
            }
        }
    }

    private function flip(string $id): void
    {
        if (ZoneKind::DrawPile === $this->zoneIds[$this->where[$id]]->kind) {
            throw IllegalGesture::because('The cards of the draw pile always lie face down.');
        }

        $this->faceUp[$id] = !$this->faceUp[$id];
    }

    private function turnFace(string $id, int $face): void
    {
        if ($face >= \count($this->deck->card(new CardId($id))->faces)) {
            throw IllegalGesture::because(\sprintf('The card "%s" has no face %d.', $id, $face));
        }

        $this->face[$id] = $face;
    }

    private function collect(Collect $collect): void
    {
        $key = $this->known($collect->from);
        $ids = $this->zones[$key];
        $this->zones[$key] = [];
        $this->put($ids, $collect->to, $collect->at, $collect->faceUp);
    }

    private function take(ZoneId $zone, Pick $pick): string
    {
        $cards = $this->zones[$this->known($zone)];

        if ([] === $cards) {
            throw IllegalGesture::because(\sprintf('The zone "%s" is empty.', $zone->toString()));
        }

        return $cards[match ($pick) {
            Pick::Top => 0,
            Pick::Bottom => \count($cards) - 1,
            Pick::Random => $this->randomizer()->getInt(0, \count($cards) - 1),
        }];
    }

    private function move(string $id, ZoneId $zone, End $at, bool $faceUp): void
    {
        $from = $this->where[$id];
        $this->zones[$from] = array_values(array_diff($this->zones[$from], [$id]));
        $this->put([$id], $zone, $at, $faceUp);
    }

    /**
     * @param list<string> $ids
     */
    private function put(array $ids, ZoneId $zone, End $at, bool $faceUp): void
    {
        $key = $zone->toString();

        if (!isset($this->zones[$key])) {
            if (\count($this->zones) >= self::MAX_ZONES) {
                throw IllegalGesture::because(\sprintf('A game opens at most %d zones.', self::MAX_ZONES));
            }

            $this->zones[$key] = [];
            $this->zoneIds[$key] = $zone;
        }

        $this->zones[$key] = End::Top === $at ? [...$ids, ...$this->zones[$key]] : [...$this->zones[$key], ...$ids];

        foreach ($ids as $id) {
            $this->where[$id] = $key;
            $this->faceUp[$id] = $faceUp && ZoneKind::DrawPile !== $zone->kind;
            $this->face[$id] ??= 0;
        }
    }

    private function known(ZoneId $zone): string
    {
        $key = $zone->toString();

        return isset($this->zones[$key]) ? $key : throw IllegalGesture::because(\sprintf('The zone "%s" is not open in this game.', $key));
    }

    private function card(CardId $card): string
    {
        return $this->deck->has($card) ? $card->value : throw IllegalGesture::because(\sprintf('The deck holds no card "%s".', $card->value));
    }

    private function randomizer(): Randomizer
    {
        return $this->seed->randomizer($this->clientSeed, $this->nonce++);
    }
}
