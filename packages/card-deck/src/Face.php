<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * What one side of a card shows, as a key the application draws — `AS`, `JK`, `back-blue`: the library compares keys
 * and never reads them.
 */
final readonly class Face
{
    public string $key;

    /**
     * @throws InvalidDefinition when the key does not follow the pattern of the names
     */
    public function __construct(string $key)
    {
        $this->key = Name::check($key, 'A face key');
    }
}
