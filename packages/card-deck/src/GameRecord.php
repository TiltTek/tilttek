<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\IllegalGesture;
use Tilttek\CardDeck\Gesture\Gesture;
use Tilttek\Dice\Fairness\Commitment;
use Tilttek\Dice\Fairness\Seed;

/**
 * What a store keeps of a game: its deck, its seed — a secret until the game is finished —, the client seed, the
 * journal of gestures, whether it is finished, and its version, one more at every change. The state is never stored:
 * it is replayed from the journal.
 */
final readonly class GameRecord
{
    /** The longest journal of a game: replaying it stays bounded. */
    public const int MAX_GESTURES = 10_000;

    /**
     * @param list<Gesture> $gestures
     * @param ?string       $clientSeed null until the game is started
     */
    public function __construct(
        public GameId $id,
        public Deck $deck,
        public Seed $seed,
        public int $version = 0,
        public ?string $clientSeed = null,
        public array $gestures = [],
        public bool $finished = false,
    ) {
    }

    /**
     * The commitment to the seed, published when the game is opened, before the client seed is chosen: it binds the
     * seed, and with it every shuffle and every random draw of the game.
     */
    public function commitment(): Commitment
    {
        return $this->seed->commitment();
    }

    /**
     * @throws IllegalGesture when the game is not started
     */
    public function state(): DeckState
    {
        return DeckState::replay($this->deck, $this->seed, $this->clientSeed ?? throw IllegalGesture::because('The game is not started.'), $this->gestures);
    }

    public function withStart(string $clientSeed): self
    {
        return new self($this->id, $this->deck, $this->seed, $this->version + 1, $clientSeed, $this->gestures, $this->finished);
    }

    public function withGesture(Gesture $gesture): self
    {
        return new self($this->id, $this->deck, $this->seed, $this->version + 1, $this->clientSeed, [...$this->gestures, $gesture], $this->finished);
    }

    public function withFinish(): self
    {
        return new self($this->id, $this->deck, $this->seed, $this->version + 1, $this->clientSeed, $this->gestures, true);
    }
}
