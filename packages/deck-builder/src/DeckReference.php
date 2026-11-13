<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use Tilttek\DeckBuilder\Exception\InvalidDeck;

/**
 * A version of a deck, named by the deck's identifier and the version's number: the original a copied deck links to.
 */
final readonly class DeckReference
{
    public string $deck;

    public int $version;

    /**
     * @throws InvalidDeck when the identifier or the version is refused
     */
    public function __construct(string $deck, int $version)
    {
        $this->deck = Identifiers::id($deck, 'deck');

        if ($version < 1) {
            throw InvalidDeck::because(\sprintf('A version of a deck is 1 or more; %d is not.', $version));
        }

        $this->version = $version;
    }

    /**
     * @throws InvalidDeck when the array does not read as a reference
     */
    public static function fromArray(mixed $data): self
    {
        $object = ArrayInput::object($data, 'deck reference', ['deck', 'version']);

        return new self(ArrayInput::string($object, 'deck', 'deck reference'), ArrayInput::int($object, 'version', 'deck reference'));
    }

    /**
     * @return array{deck: string, version: int}
     */
    public function toArray(): array
    {
        return ['deck' => $this->deck, 'version' => $this->version];
    }
}
