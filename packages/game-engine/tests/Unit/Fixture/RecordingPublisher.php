<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit\Fixture;

use Tilttek\GameEngine\Contract\TablePublisher;
use Tilttek\GameEngine\TableUpdate;

final class RecordingPublisher implements TablePublisher
{
    /** @var list<TableUpdate> */
    public array $updates = [];

    public function publish(TableUpdate $update): void
    {
        $this->updates[] = $update;
    }
}
