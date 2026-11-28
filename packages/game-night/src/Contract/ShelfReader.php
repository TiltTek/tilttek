<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Contract;

use Tilttek\GameNight\Choice\OwnedGame;

/**
 * Reads the games the members own and can bring, from the application's shelves: the library never reads a member's
 * collection otherwise.
 */
interface ShelfReader
{
    /**
     * @param list<string> $members the members whose games to read
     * @param int          $limit   the most games to give
     *
     * @return iterable<OwnedGame>
     */
    public function games(array $members, int $limit): iterable;
}
