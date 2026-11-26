<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * The identity of a card, opaque and separate from its faces: two jokers have the same face and two identifiers. A
 * hidden card is never shown by its identifier.
 */
final readonly class CardId
{
    public string $value;

    /**
     * @throws InvalidDefinition when the value does not follow the pattern of the names
     */
    public function __construct(string $value)
    {
        $this->value = Name::check($value, 'A card identifier');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
