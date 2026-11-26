<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\CardDeckException;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\Gesture\Gesture;
use Tilttek\CardDeck\Gesture\GestureCodec;
use Tilttek\Dice\Exception\InvalidDefinition as InvalidSeed;
use Tilttek\Dice\Fairness\Commitment;
use Tilttek\Dice\Fairness\Seed;

/**
 * What is published once a game is finished: the deck, the seed, the client seed and the journal of gestures. Anyone
 * checks that the seed matches the commitment published at the start, and replays the game to the same state.
 */
final readonly class Reveal
{
    /**
     * @param list<Gesture> $gestures
     */
    public function __construct(
        public Deck $deck,
        public Seed $seed,
        public string $clientSeed,
        public array $gestures,
    ) {
    }

    /**
     * Whether the seed is the one the commitment published at the start of the game binds.
     */
    public function matches(Commitment $published): bool
    {
        return $published->matches($this->seed);
    }

    /**
     * The state the journal leads to from the seed: the same, fingerprint and all, as the one the server held.
     *
     * @throws CardDeckException when a gesture of the journal cannot be applied
     */
    public function replay(): DeckState
    {
        return DeckState::replay($this->deck, $this->seed, $this->clientSeed, $this->gestures);
    }

    /**
     * @return array{deck: list<array{id: string, faces: list<string>, back: string}>, seed: string, client_seed: string, gestures: list<array<string, scalar|list<string>>>}
     */
    public function toArray(): array
    {
        return [
            'deck' => $this->deck->toArray(),
            'seed' => $this->seed->hex(),
            'client_seed' => $this->clientSeed,
            'gestures' => array_map(static fn (Gesture $gesture): array => $gesture->toArray(), $this->gestures),
        ];
    }

    /**
     * @throws InvalidDefinition when the data is not a published game `toArray()` could have written
     */
    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data) || !\is_string($data['seed'] ?? null) || !\is_string($data['client_seed'] ?? null) || !\is_array($data['gestures'] ?? null) || !array_is_list($data['gestures']) || \count($data['gestures']) > GameRecord::MAX_GESTURES) {
            throw InvalidDefinition::because(\sprintf('A published game is a deck, a seed, a client seed and at most %d gestures.', GameRecord::MAX_GESTURES));
        }

        try {
            $seed = Seed::fromHex($data['seed']);
        } catch (InvalidSeed $invalidSeed) {
            throw InvalidDefinition::because($invalidSeed->getMessage());
        }

        return new self(Deck::fromArray($data['deck'] ?? null), $seed, $data['client_seed'], array_map(GestureCodec::fromArray(...), $data['gestures']));
    }
}
