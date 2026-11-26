<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Benchmark;

use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\DeckState;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Discard;
use Tilttek\CardDeck\Gesture\Draw;
use Tilttek\CardDeck\Gesture\Gesture;
use Tilttek\CardDeck\Gesture\Move;
use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\CardDeck\ZoneId;
use Tilttek\Dice\Fairness\Seed;

/**
 * What a request of a card game does: replay the journal from the seed, then build the view of one player. A journal
 * of 1 000 gestures on a deck of 54 cards stands for a long game; one of 10 000, the bound of a game.
 */
final readonly class ReplayBench
{
    private Seed $seed;

    /** @var list<Gesture> */
    private array $long;

    /** @var list<Gesture> */
    private array $bound;

    public function __construct()
    {
        $this->seed = Seed::fromHex(str_repeat('ab', 32));
        $this->long = $this->journal(1_000);
        $this->bound = $this->journal(10_000);
    }

    public function benchReplayOneThousandGestures(): void
    {
        DeckState::replay(StandardDeck::create(2), $this->seed, '', $this->long)->seenBy(new PlayerId('p1'));
    }

    public function benchReplayTenThousandGestures(): void
    {
        DeckState::replay(StandardDeck::create(2), $this->seed, '', $this->bound)->seenBy(new PlayerId('p1'));
    }

    /**
     * @return list<Gesture>
     */
    private function journal(int $length): array
    {
        $players = [new PlayerId('p1'), new PlayerId('p2'), new PlayerId('p3'), new PlayerId('p4')];
        $gestures = [];

        while (\count($gestures) < $length) {
            $gestures[] = new Deal($players, 5);

            foreach (range(1, 20) as $index) {
                $gestures[] = new Move(new CardId(\sprintf('card-%02d', $index)), ZoneId::table(), faceUp: true);
            }

            $gestures[] = new Draw($players[0]);
            $gestures[] = new Discard(new CardId('card-30'));

            foreach (range(1, 54) as $index) {
                $gestures[] = new Move(new CardId(\sprintf('card-%02d', $index)), ZoneId::drawPile());
            }

            $gestures[] = new Shuffle();
        }

        return \array_slice($gestures, 0, $length);
    }
}
