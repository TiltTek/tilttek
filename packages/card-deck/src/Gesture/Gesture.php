<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

/**
 * One gesture at the table, recorded in the journal of the game: replaying the journal from the seed gives the same
 * state. A gesture is a value; `GestureCodec` reads back what `toArray()` writes.
 */
interface Gesture
{
    /**
     * @return array<string, scalar|list<string>>
     */
    public function toArray(): array;
}
