<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Tests\Unit\Fixture;

use Tilttek\Crafting\Contract\CraftObserver;
use Tilttek\Crafting\Crafted;

/**
 * An observer that keeps what it is told.
 */
final class Recorder implements CraftObserver
{
    /** @var list<Crafted> */
    public array $events = [];

    public function crafted(Crafted $event): void
    {
        $this->events[] = $event;
    }
}
