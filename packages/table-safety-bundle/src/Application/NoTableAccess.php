<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Application;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafetyBundle\Application\Contract\TableAccess;

/**
 * The access of an application that has not said who takes part in its tables: nobody does, so every screen is
 * refused until the application registers its own `TableAccess`.
 */
final readonly class NoTableAccess implements TableAccess
{
    #[Override]
    public function participant(ResourceReference $table): ?ResourceReference
    {
        return null;
    }

    #[Override]
    public function isGameMaster(ResourceReference $table): bool
    {
        return false;
    }
}
