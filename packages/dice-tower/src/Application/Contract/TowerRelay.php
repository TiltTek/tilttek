<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application\Contract;

use Tilttek\DiceTower\Application\TowerEntry;

/**
 * Carries a new entry of a tower somewhere else — a chat channel of the table, for instance —, by an adapter of its
 * own package. Every relay tagged `tilttek_dice_tower.relay` receives every entry; one that fails is logged and does
 * not stop the others.
 */
interface TowerRelay
{
    public function relay(TowerEntry $entry): void;
}
