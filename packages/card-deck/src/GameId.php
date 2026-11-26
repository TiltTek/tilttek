<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * The opaque identifier of a game, the key of its record in the store.
 */
final readonly class GameId
{
    public string $value;

    /**
     * @throws InvalidDefinition when the value does not follow the pattern of the names
     */
    public function __construct(string $value)
    {
        $this->value = Name::check($value, 'A game identifier');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
