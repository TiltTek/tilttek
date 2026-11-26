<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * The cards of a game, in the order they are declared: 1 to `MAX_CARDS` cards, each identifier once. It is the order of
 * the draw pile before the shuffle that starts a game.
 */
final readonly class Deck
{
    public const int MAX_CARDS = 1024;

    /** @var non-empty-array<string, Card> by identifier, in the declared order */
    private array $cards;

    /**
     * @throws InvalidDefinition when the deck is empty, too large, or holds an identifier twice
     */
    public function __construct(Card ...$cards)
    {
        if ([] === $cards || \count($cards) > self::MAX_CARDS) {
            throw InvalidDefinition::because(\sprintf('A deck holds 1 to %d cards.', self::MAX_CARDS));
        }

        $byId = [];

        foreach ($cards as $card) {
            if (isset($byId[$card->id->value])) {
                throw InvalidDefinition::because(\sprintf('The card "%s" is twice in the deck.', $card->id->value));
            }

            $byId[$card->id->value] = $card;
        }

        $this->cards = $byId;
    }

    /**
     * @return non-empty-list<Card>
     */
    public function cards(): array
    {
        return array_values($this->cards);
    }

    /**
     * @throws InvalidDefinition when the deck holds no such card
     */
    public function card(CardId $id): Card
    {
        return $this->cards[$id->value] ?? throw InvalidDefinition::because(\sprintf('The deck holds no card "%s".', $id->value));
    }

    public function has(CardId $id): bool
    {
        return isset($this->cards[$id->value]);
    }

    /**
     * The deck as plain data, for a store or a published game: `fromArray()` reads it back.
     *
     * @return list<array{id: string, faces: list<string>, back: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (Card $card): array => [
            'id' => $card->id->value,
            'faces' => array_map(static fn (Face $face): string => $face->key, $card->faces),
            'back' => $card->back->key,
        ], $this->cards());
    }

    /**
     * @throws InvalidDefinition when the data is not a deck `toArray()` could have written
     */
    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data) || !array_is_list($data)) {
            throw InvalidDefinition::because('A deck is a list of cards.');
        }

        $cards = [];

        foreach ($data as $card) {
            if (!\is_array($card) || !\is_string($card['id'] ?? null) || !\is_string($card['back'] ?? null) || !\is_array($card['faces'] ?? null) || !array_is_list($card['faces'])) {
                throw InvalidDefinition::because('A card is an identifier, a list of faces and a back.');
            }

            $faces = [];

            foreach ($card['faces'] as $face) {
                if (!\is_string($face)) {
                    throw InvalidDefinition::because('A face is a key.');
                }

                $faces[] = new Face($face);
            }

            $cards[] = new Card(new CardId($card['id']), $faces, new Face($card['back']));
        }

        return new self(...$cards);
    }
}
