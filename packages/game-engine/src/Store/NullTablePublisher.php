<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Store;

use Tilttek\GameEngine\Contract\TablePublisher;
use Tilttek\GameEngine\TableUpdate;

/**
 * No publication: the players read the table again on their own.
 */
final readonly class NullTablePublisher implements TablePublisher
{
    public function publish(TableUpdate $update): void
    {
    }
}
