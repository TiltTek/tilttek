<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use Tilttek\DeckBuilder\Exception\InvalidDeck;

/**
 * A card as the application's card catalog gives it: a stable identifier, the name shown in messages, and the number of
 * copies the card itself allows when it differs from the format's — a card any number of which may be played, or a
 * card limited to one.
 */
final readonly class Card
{
    /**
     * The most copies of one card a deck holds.
     */
    public const int MAX_COPIES = 999;

    public string $id;

    public string $name;

    /**
     * The copies the card allows, from 1 to 999; null for the format's number.
     */
    public ?int $copyLimit;

    /**
     * @throws InvalidDeck when the identifier, the name or the copy limit is refused
     */
    public function __construct(string $id, string $name, ?int $copyLimit = null)
    {
        $this->id = Identifiers::id($id, 'card');
        $this->name = Identifiers::name($name, 'card');

        if (null !== $copyLimit && ($copyLimit < 1 || $copyLimit > self::MAX_COPIES)) {
            throw InvalidDeck::because(\sprintf('The copy limit of the card "%s" is from 1 to %d; %d is not.', $id, self::MAX_COPIES, $copyLimit));
        }

        $this->copyLimit = $copyLimit;
    }

    /**
     * The card's name and identifier, as messages show them.
     */
    public function label(): string
    {
        return \sprintf('"%s" (%s)', $this->name, $this->id);
    }
}
