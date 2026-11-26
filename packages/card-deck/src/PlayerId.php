<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * The opaque identifier of a player at the table: the application maps it to its own accounts, never a name or an
 * address.
 */
final readonly class PlayerId
{
    public string $value;

    /**
     * @throws InvalidDefinition when the value does not follow the pattern of the names
     */
    public function __construct(string $value)
    {
        $this->value = Name::check($value, 'A player identifier');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
