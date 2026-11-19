<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application\Contract;

use Tilttek\DiceTower\Application\TowerEntry;

/**
 * Pushes a new entry of a tower to the viewers of its table. A publisher that cannot reach them does not fail the
 * roll: the entry is in the log, and the viewers read what they missed from it.
 */
interface TowerPublisher
{
    public function publish(TowerEntry $entry): void;
}
