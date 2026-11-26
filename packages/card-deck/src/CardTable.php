<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Contract\GameStore;
use Tilttek\CardDeck\Exception\ConcurrentChange;
use Tilttek\CardDeck\Exception\IllegalGesture;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\Exception\UnknownGame;
use Tilttek\CardDeck\Gesture\Gesture;
use Tilttek\CardDeck\View\PlayerView;
use Tilttek\Dice\Fairness\Commitment;
use Tilttek\Dice\Fairness\Seed;

/**
 * The games of a table, through a store: open a game and publish the commitment to its seed, start it with the seed
 * the players chose, play gestures against the version a player saw, give each player their view, and finish it to
 * reveal the seed. It keeps no state between two calls.
 */
final readonly class CardTable
{
    public function __construct(
        private GameStore $store,
    ) {
    }

    /**
     * Opens a game and returns the commitment to publish before the players choose their seed.
     *
     * @param Seed $seed drawn from a secure source, `Seed::generate(new Randomizer(new Secure()))`
     *
     * @throws ConcurrentChange when the store already holds a game of this identifier
     */
    public function open(GameId $id, Deck $deck, Seed $seed): Commitment
    {
        $record = new GameRecord($id, $deck, $seed);
        $this->store->save($record);

        return $record->commitment();
    }

    /**
     * Starts the game: the draw pile shuffled from the seed and the client seed.
     *
     * @throws UnknownGame
     * @throws IllegalGesture    when the game is already started
     * @throws InvalidDefinition when the client seed is too long
     * @throws ConcurrentChange
     */
    public function start(GameId $id, string $clientSeed = ''): DeckState
    {
        $record = $this->record($id);

        if (null !== $record->clientSeed) {
            throw IllegalGesture::because('The game is already started.');
        }

        $state = DeckState::start($record->deck, $record->seed, $clientSeed);
        $this->store->save($record->withStart($clientSeed));

        return $state;
    }

    /**
     * Applies a gesture to the game, when the version the player saw is still the stored one.
     *
     * @throws UnknownGame
     * @throws ConcurrentChange when the game changed since that version
     * @throws IllegalGesture   when the game is not started, finished, full, or refuses the gesture
     */
    public function play(GameId $id, int $version, Gesture $gesture): DeckState
    {
        $record = $this->record($id);

        if ($record->version !== $version) {
            throw ConcurrentChange::because(\sprintf('The game "%s" is at version %d, not %d.', $id->value, $record->version, $version));
        }

        $this->playable($record);

        if (\count($record->gestures) >= GameRecord::MAX_GESTURES) {
            throw IllegalGesture::because(\sprintf('A game records at most %d gestures.', GameRecord::MAX_GESTURES));
        }

        $state = $record->state()->apply($gesture);
        $this->store->save($record->withGesture($gesture));

        return $state;
    }

    /**
     * What one player may see of the game, or a spectator with no player.
     *
     * @throws UnknownGame
     * @throws IllegalGesture when the game is not started
     */
    public function view(GameId $id, ?PlayerId $viewer = null): PlayerView
    {
        $record = $this->record($id);

        return new PlayerView($viewer, $record->commitment()->hex, $record->version, $record->finished, $record->state()->seenBy($viewer));
    }

    /**
     * Finishes the game and reveals what anyone needs to verify it.
     *
     * @throws UnknownGame
     * @throws IllegalGesture   when the game is not started or already finished
     * @throws ConcurrentChange
     */
    public function finish(GameId $id): Reveal
    {
        $record = $this->record($id);
        $this->playable($record);
        $this->store->save($record->withFinish());

        return new Reveal($record->deck, $record->seed, (string) $record->clientSeed, $record->gestures);
    }

    private function record(GameId $id): GameRecord
    {
        return $this->store->find($id) ?? throw UnknownGame::because(\sprintf('The store holds no game "%s".', $id->value));
    }

    private function playable(GameRecord $record): void
    {
        if (null === $record->clientSeed) {
            throw IllegalGesture::because('The game is not started.');
        }

        if ($record->finished) {
            throw IllegalGesture::because('The game is finished.');
        }
    }
}
