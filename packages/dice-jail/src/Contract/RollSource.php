<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Contract;

use DateTimeImmutable;

/**
 * Where the rolls of a die are read: the faces a player wrote down for a physical die, or the journal of rolls an
 * application keeps for its digital dice.
 */
interface RollSource
{
    /**
     * The faces the die showed, one value per roll, in any order.
     *
     * @param ?DateTimeImmutable $since the first moment counted; every roll when null
     *
     * @return iterable<int>
     */
    public function faces(string $die, ?DateTimeImmutable $since = null): iterable;
}
