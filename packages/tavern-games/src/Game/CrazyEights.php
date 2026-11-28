<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Game;

use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\DeckState;
use Tilttek\CardDeck\Exception\CardDeckException;
use Tilttek\CardDeck\Gesture\Collect;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Discard;
use Tilttek\CardDeck\Gesture\Draw;
use Tilttek\CardDeck\Gesture\End;
use Tilttek\CardDeck\Gesture\Gesture;
use Tilttek\CardDeck\Gesture\GestureCodec;
use Tilttek\CardDeck\Gesture\Move as MoveCard;
use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\CardDeck\View\SeenCard;
use Tilttek\CardDeck\ZoneId;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Option;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Phase;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Statistic;
use Tilttek\GameEngine\Statistics;
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\Turns;

/**
 * Crazy Eights, the old card game of shedding: each seat is dealt a hand from a 52-card deck and one card is turned up.
 * In their turn, a seat plays a card of the same rank or suit as the top of the discard pile, or an eight, which is
 * wild and names the suit to follow; a seat that cannot or will not play draws one card, then plays or passes. The
 * first seat to empty its hand wins. When nothing is left to draw and every seat passes in a row, or after
 * `MAX_GESTURES` gestures, the fewest cards win, equal hands sharing the win.
 *
 * The cards are kept by `tilttek/card-deck`, shuffled from a seed drawn from the table's own seed, so that replaying
 * the table deals the same cards. Each seat sees its own hand, the top of the discard pile, and how many cards the
 * others hold; never their cards, the order of the draw pile or the seed.
 *
 * Its moves are `crazy-eights.play` (`{card, suit}`, the suit `S`, `H`, `D` or `C` for an eight only),
 * `crazy-eights.draw` and `crazy-eights.pass`; the timeout of a turn draws a card when none was drawn, then passes.
 * Its option is `hand`, the cards dealt to each seat (5, from 3 to 8). Its outcome reports the cards left in each hand
 * as the statistic `cards`.
 */
final readonly class CrazyEights implements TavernGame
{
    public const string NAME = 'crazy-eights';

    public const string PLAY = 'crazy-eights.play';

    public const string DRAW = 'crazy-eights.draw';

    public const string PASS = 'crazy-eights.pass';

    /** The most gestures of a game: the replay of each move stays bounded. */
    public const int MAX_GESTURES = 1_000;

    private const array SUITS = ['S', 'H', 'D', 'C'];

    private GameDefinition $definition;

    private Deck $deck;

    public function __construct()
    {
        $this->definition = new GameDefinition(
            self::NAME,
            [new Phase('playing')],
            minimumSeats: 2,
            maximumSeats: 6,
            options: [Option::integer('hand', 5, 3, 8)],
            statistics: [Statistic::seat('cards')],
        );
        $this->deck = StandardDeck::create();
    }

    public function definition(): GameDefinition
    {
        return $this->definition;
    }

    public function setup(GameState $state, Randomness $random): array
    {
        $seed = sodium_bin2hex($random->randomizer()->getBytes(Seed::BYTES));
        $deal = new Deal(array_map(self::player(...), array_keys($state->seats)), $state->options->int('hand'));
        $dealt = DeckState::start($this->deck, Seed::fromHex($seed))->apply($deal);
        $turnUp = new MoveCard($dealt->cardsIn(ZoneId::drawPile())[0], ZoneId::discard(), End::Top, true);
        $top = $this->face($dealt, $turnUp->card);

        return [
            'seed' => $seed,
            'gestures' => [$deal->toArray(), $turnUp->toArray()],
            'turns' => Turns::start($state->seats)->toData(),
            'suit' => '8' === $this->rank($top) ? $this->suit($top) : null,
            'drawn' => false,
            'passes' => 0,
        ];
    }

    public function allows(GameState $state, Move $move): bool
    {
        $turns = Turns::fromData($state->data['turns'] ?? null);

        return match ($move->type) {
            self::PLAY, self::DRAW, self::PASS => $turns->isTurnOf($move->seat),
            self::TIMEOUT => null === $move->seat && $turns->isTurnOf($move->text('seat')),
            default => false,
        };
    }

    public function play(GameState $state, Move $move, Randomness $random): array
    {
        $data = $state->data;
        $cards = $this->cards($data);
        $turns = Turns::fromData($data['turns'] ?? null);
        $player = self::player($turns->placeOf($turns->seat()));
        $drawn = true === ($data['drawn'] ?? null);

        if (self::PLAY === $move->type) {
            return $this->played($data, $cards, $turns, $player, $move);
        }

        if (self::DRAW === $move->type) {
            if ($drawn) {
                throw IllegalMove::because('A seat draws one card a turn.');
            }

            return $this->drew($data, $cards, $player);
        }

        // A pass, or the timeout of a turn, which draws a card first while one is left and none was drawn.
        $drewNow = false;

        if (!$drawn && $this->canDraw($cards)) {
            if (self::PASS === $move->type) {
                throw IllegalMove::because('A seat draws a card before it passes, while a card is left to draw.');
            }

            $data = $this->drew($data, $cards, $player);
            $drewNow = true;
        }

        $data['passes'] = $drawn || $drewNow ? 0 : $this->passes($data) + 1;
        $data['drawn'] = false;
        $data['turns'] = $turns->next()->toData();

        return $data;
    }

    public function enter(GameState $state, Randomness $random): array
    {
        return $state->data;
    }

    public function outcome(GameState $state): ?Outcome
    {
        $cards = $this->cards($state->data);
        $left = [];

        foreach ($state->seats as $place => $seat) {
            $left[$seat] = $cards->count(ZoneId::hand(self::player($place)));
        }

        $statistics = new Statistics([], array_map(static fn (int $count): array => ['cards' => $count], $left));
        $fewest = min(Deck::MAX_CARDS, ...array_values($left));
        $winners = array_keys(array_filter($left, static fn (int $count): bool => $count === $fewest));

        if (0 === $fewest) {
            return Outcome::won($winners, 'emptied-hand', $statistics);
        }

        if ($this->passes($state->data) >= \count($state->seats)) {
            return Outcome::won($winners, 'blocked', $statistics);
        }

        return \count($this->gestures($state->data)) >= self::MAX_GESTURES ? Outcome::won($winners, 'too-long', $statistics) : null;
    }

    public function toAct(GameState $state): ?string
    {
        return $state->isOver() ? null : Turns::fromData($state->data['turns'] ?? null)->seat();
    }

    /**
     * The seat's own hand, the top of the discard pile and the suit to follow, the cards left to draw, how many cards
     * each seat holds, the seat playing and whether it has drawn; for the audience, no hand.
     */
    public function view(GameState $state, ?string $seat, Preferences $preferences): array
    {
        $cards = $this->cards($state->data);
        $turns = Turns::fromData($state->data['turns'] ?? null);
        $hand = [];
        $hands = [];

        if (null !== $seat) {
            $own = ZoneId::hand(self::player($turns->placeOf($seat)));

            foreach ($cards->seenBy(self::player($turns->placeOf($seat))) as $zone) {
                $hand = $zone->zone->equals($own) ? array_map(static fn (SeenCard $card): array => ['card' => $card->id?->value, 'face' => $card->face?->key], $zone->cards) : $hand;
            }
        }

        foreach ($state->seats as $place => $at) {
            $hands[$at] = $cards->count(ZoneId::hand(self::player($place)));
        }

        $top = $this->face($cards, $this->top($cards));

        return [
            'hand' => $hand,
            'top' => $top,
            'suit' => $state->data['suit'] ?? $this->suit($top),
            'draw' => $cards->count(ZoneId::drawPile()),
            'hands' => $hands,
            'turn' => $state->isOver() ? null : $turns->seat(),
            'drawn' => true === ($state->data['drawn'] ?? null),
        ];
    }

    /**
     * The player of `tilttek/card-deck` of the seat at a place of the table, from 0: `p1`, `p2`….
     */
    public static function player(int $place): PlayerId
    {
        return new PlayerId('p'.($place + 1));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws IllegalMove
     */
    private function played(array $data, DeckState $cards, Turns $turns, PlayerId $player, Move $move): array
    {
        try {
            $card = new CardId($move->text('card') ?? '');
            $inHand = $cards->zoneOf($card)->isHandOf($player);
        } catch (CardDeckException) {
            throw IllegalMove::because('A seat plays a card of its hand: {"card": "card-07"}.');
        }

        $face = $this->face($cards, $card);
        $top = $this->face($cards, $this->top($cards));
        $follow = \is_string($data['suit'] ?? null) ? $data['suit'] : $this->suit($top);
        $eight = '8' === $this->rank($face);
        $called = $move->text('suit');

        if (!$inHand) {
            throw IllegalMove::because('A seat plays a card of its hand: {"card": "card-07"}.');
        }

        if (!$eight && $this->suit($face) !== $follow && $this->rank($face) !== $this->rank($top)) {
            throw IllegalMove::because(\sprintf('The card is an eight, or follows the suit %s or the rank of the top card.', $follow));
        }

        if ($eight && !\in_array($called, self::SUITS, true)) {
            throw IllegalMove::because('An eight names the suit to follow: {"suit": "S", "H", "D" or "C"}.');
        }

        $data['gestures'] = [...$this->gestures($data), new Discard($card)->toArray()];
        $data['suit'] = $eight ? $called : null;
        $data['drawn'] = false;
        $data['passes'] = 0;
        $data['turns'] = $turns->next()->toData();

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws IllegalMove
     */
    private function drew(array $data, DeckState $cards, PlayerId $player): array
    {
        $gestures = [];

        if ($cards->isEmpty(ZoneId::drawPile())) {
            if (!$this->canDraw($cards)) {
                throw IllegalMove::because('No card is left to draw: the seat passes.');
            }

            $top = $this->top($cards);
            $gestures = [new Collect(ZoneId::discard(), ZoneId::drawPile()), new Shuffle(), new MoveCard($top, ZoneId::discard(), End::Top, true)];
        }

        $gestures[] = new Draw($player);
        $data['gestures'] = [...$this->gestures($data), ...array_map(static fn (Gesture $gesture): array => $gesture->toArray(), $gestures)];
        $data['drawn'] = true;

        return $data;
    }

    /**
     * Whether a card is left to draw: in the draw pile, or under the top of the discard pile, shuffled back.
     */
    private function canDraw(DeckState $cards): bool
    {
        return !$cards->isEmpty(ZoneId::drawPile()) || $cards->count(ZoneId::discard()) > 1;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidGame
     */
    private function cards(array $data): DeckState
    {
        $seed = $data['seed'] ?? null;

        try {
            return DeckState::replay($this->deck, Seed::fromHex(\is_string($seed) ? $seed : ''), '', array_map(GestureCodec::fromArray(...), $this->gestures($data)));
        } catch (CardDeckException $cardDeckException) {
            throw InvalidGame::because('The cards of Crazy Eights do not replay: '.$cardDeckException->getMessage(), $cardDeckException);
        } catch (DiceException $diceException) {
            throw InvalidGame::because('The seed of the cards of Crazy Eights is not 64 hexadecimal digits.', $diceException);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<mixed>
     *
     * @throws InvalidGame
     */
    private function gestures(array $data): array
    {
        $gestures = $data['gestures'] ?? null;

        return \is_array($gestures) ? array_values($gestures) : throw InvalidGame::because('The gestures of Crazy Eights are a list.');
    }

    /**
     * The passes in a row made with nothing left to draw.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidGame
     */
    private function passes(array $data): int
    {
        $passes = $data['passes'] ?? null;

        if (!\is_int($passes)) {
            throw InvalidGame::because('The passes of Crazy Eights are a whole number.');
        }

        return $passes;
    }

    private function top(DeckState $cards): CardId
    {
        return $cards->cardsIn(ZoneId::discard())[0];
    }

    private function face(DeckState $cards, CardId $card): string
    {
        return $cards->faceOf($card)->key;
    }

    private function rank(string $face): string
    {
        return substr($face, 0, -1);
    }

    private function suit(string $face): string
    {
        return substr($face, -1);
    }
}
