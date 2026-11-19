<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceTower\Application\Contract\TableAccess;

/**
 * Lets nobody watch a tower, until the application names the service that knows its tables.
 */
final readonly class NoTableAccess implements TableAccess
{
    #[Override]
    public function canWatch(ResourceReference $table): bool
    {
        return false;
    }
}
