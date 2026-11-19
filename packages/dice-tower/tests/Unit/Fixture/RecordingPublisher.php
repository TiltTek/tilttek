<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Fixture;

use Override;
use Tilttek\DiceTower\Application\Contract\TowerPublisher;
use Tilttek\DiceTower\Application\TowerEntry;

/**
 * A publisher that keeps what it is given.
 */
final class RecordingPublisher implements TowerPublisher
{
    /** @var list<TowerEntry> */
    public array $entries = [];

    #[Override]
    public function publish(TowerEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}
