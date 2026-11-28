<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Unit\Fixture;

use Override;
use Tilttek\ReferenceTables\Contract\TableObserver;
use Tilttek\ReferenceTables\Event\TableRolled;

/**
 * Records what the roller tells, in order.
 */
final class Observed implements TableObserver
{
    /** @var list<TableRolled> */
    public array $events = [];

    #[Override]
    public function notify(TableRolled $event): void
    {
        $this->events[] = $event;
    }
}
