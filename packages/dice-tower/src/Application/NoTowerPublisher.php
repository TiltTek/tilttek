<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application;

use Override;
use Tilttek\DiceTower\Application\Contract\TowerPublisher;

/**
 * Publishes nothing: without a hub, the viewers read the tower from its log.
 */
final readonly class NoTowerPublisher implements TowerPublisher
{
    #[Override]
    public function publish(TowerEntry $entry): void
    {
    }
}
