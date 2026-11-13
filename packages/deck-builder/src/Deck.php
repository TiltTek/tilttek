<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use Tilttek\DeckBuilder\Contract\OwnedCards;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

/**
 * A deck: its identifier, its name, the format it is built for, the cards it holds with their quantities in the order
 * they were added, and the original it was copied from. A deck never changes: each change gives a new deck, which
 * the application saves as a new version.
 */
final readonly class Deck
{
    /**
     * The most distinct cards of a deck.
     */
    public const int MAX_CARDS = 1_000;

    public string $id;

    public string $name;

    public string $format;

    /**
     * The identifiers of the cards, each with its quantity, 1 to 999, in the order they were added.
     *
     * @var array<array-key, int>
     */
    public array $cards;

    /**
     * @param array<array-key, int> $cards the identifiers of the cards, each with its quantity
     *
     * @throws InvalidDeck when the identifier, the name, the format, a card or a quantity is refused
     */
    public function __construct(string $id, string $name, string $format, array $cards = [], public ?DeckReference $copiedFrom = null)
    {
        $this->id = Identifiers::id($id, 'deck');
        $this->name = Identifiers::name($name, 'deck');
        $this->format = Identifiers::code($format, 'format');

        if (\count($cards) > self::MAX_CARDS) {
            throw InvalidDeck::because(\sprintf('A deck holds at most %d distinct cards.', self::MAX_CARDS));
        }

        $checked = [];

        foreach ($cards as $card => $quantity) {
            $checked[$this->card((string) $card)] = $this->quantity((string) $card, $quantity);
        }

        $this->cards = $checked;
    }

    /**
     * @throws InvalidDeck when the array does not read as a deck
     */
    public static function fromArray(mixed $data): self
    {
        $object = ArrayInput::object($data, 'deck', ['id', 'name', 'format'], ['cards', 'copied_from']);
        $copiedFrom = $object['copied_from'] ?? null;

        return new self(
            ArrayInput::string($object, 'id', 'deck'),
            ArrayInput::string($object, 'name', 'deck'),
            ArrayInput::string($object, 'format', 'deck'),
            ArrayInput::counts($object, 'cards', 'deck', self::MAX_CARDS),
            null === $copiedFrom ? null : DeckReference::fromArray($copiedFrom),
        );
    }

    /**
     * @throws InvalidDeck when the text is not JSON or does not read as a deck
     */
    public static function fromJson(string $json): self
    {
        return self::fromArray(ArrayInput::json($json, 'deck'));
    }

    /**
     * The deck with more copies of a card, added at the end when the deck did not hold it.
     *
     * @throws InvalidDeck when the card or the quantity is refused, or the deck would hold more than 999 copies
     */
    public function withCard(string $card, int $quantity = 1): self
    {
        $this->quantity($card, $quantity);

        return $this->withQuantity($card, $this->quantityOf($card) + $quantity);
    }

    /**
     * The deck with fewer copies of a card; none of them left removes it.
     *
     * @throws InvalidDeck when the quantity is not 1 or more
     */
    public function withoutCard(string $card, int $quantity = 1): self
    {
        $this->quantity($card, $quantity);

        return $this->withQuantity($card, max(0, $this->quantityOf($card) - $quantity));
    }

    /**
     * The deck holding that many copies of a card, in its place; 0 removes it.
     *
     * @throws InvalidDeck when the card or the quantity is refused
     */
    public function withQuantity(string $card, int $quantity): self
    {
        $cards = $this->cards;

        if (0 === $quantity) {
            unset($cards[$card]);
        } else {
            $cards[$card] = $quantity;
        }

        return new self($this->id, $this->name, $this->format, $cards, $this->copiedFrom);
    }

    /**
     * @throws InvalidDeck when the name is refused
     */
    public function renamed(string $name): self
    {
        return new self($this->id, $name, $this->format, $this->cards, $this->copiedFrom);
    }

    /**
     * The deck as a copy: a new identifier, a name, and a link to the version it was copied from.
     *
     * @throws InvalidDeck when the identifier or the name is refused
     */
    public function copiedAs(string $id, string $name, DeckReference $original): self
    {
        return new self($id, $name, $this->format, $this->cards, $original);
    }

    /**
     * The deck holding no more copies of each card than the collection owns; a card it owns none of is removed.
     */
    public function limitedTo(OwnedCards $owned): self
    {
        $cards = [];

        foreach ($this->cards as $card => $quantity) {
            $kept = min($quantity, $owned->copiesOf(Identifiers::key($card)));

            if ($kept > 0) {
                $cards[$card] = $kept;
            }
        }

        return new self($this->id, $this->name, $this->format, $cards, $this->copiedFrom);
    }

    public function quantityOf(string $card): int
    {
        return $this->cards[$card] ?? 0;
    }

    /**
     * The number of cards, every copy counted.
     */
    public function size(): int
    {
        return array_sum($this->cards);
    }

    /**
     * @return array{id: string, name: string, format: string, cards: array<array-key, int>, copied_from: array{deck: string, version: int}|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'format' => $this->format,
            'cards' => $this->cards,
            'copied_from' => $this->copiedFrom?->toArray(),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_FORCE_OBJECT);
    }

    /**
     * Whether the two decks hold the same name, format, cards in the same order and original.
     */
    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    private function card(string $card): string
    {
        return Identifiers::id($card, 'card');
    }

    /**
     * @throws InvalidDeck when the quantity is not from 1 to 999
     */
    private function quantity(string $card, int $quantity): int
    {
        if ($quantity < 1 || $quantity > Card::MAX_COPIES) {
            throw InvalidDeck::because(\sprintf('A deck holds 1 to %d copies of the card "%s"; %d is not.', Card::MAX_COPIES, substr($card, 0, 64), $quantity));
        }

        return $quantity;
    }
}
