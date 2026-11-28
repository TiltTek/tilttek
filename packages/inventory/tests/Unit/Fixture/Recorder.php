<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Tests\Unit\Fixture;

use Tilttek\Inventory\Contract\InventoryObserver;
use Tilttek\Inventory\InventoryChanged;

/**
 * An observer that keeps what it was told, for the tests to read.
 */
final class Recorder implements InventoryObserver
{
    /** @var list<InventoryChanged> */
    public array $changes = [];

    public function changed(InventoryChanged $event): void
    {
        $this->changes[] = $event;
    }

    /**
     * The changes told, each as `inventory change item by`, a dash for a missing value.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        return array_map(static fn (InventoryChanged $event): string => \sprintf('%s %s %s %s', $event->inventory, $event->change->value, $event->item ?? '-', $event->by ?? '-'), $this->changes);
    }
}
