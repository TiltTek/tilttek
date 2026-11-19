<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Fixture;

use Override;
use RuntimeException;
use Tilttek\DiceTower\Application\Contract\TowerRelay;
use Tilttek\DiceTower\Application\TowerEntry;

/**
 * A relay that keeps what it is given, or fails when asked to.
 */
final class RecordingRelay implements TowerRelay
{
    /** @var list<TowerEntry> */
    public array $entries = [];

    public function __construct(
        private readonly bool $failing = false,
    ) {
    }

    #[Override]
    public function relay(TowerEntry $entry): void
    {
        if ($this->failing) {
            throw new RuntimeException('The channel is closed.');
        }

        $this->entries[] = $entry;
    }
}
