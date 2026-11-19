<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafetyBundle\Application\Contract\TableAccess;

/**
 * The tables of a test: the viewer takes part in `table:7` under the reference the test sets, and runs it when the
 * test says so.
 */
final class Tables implements TableAccess
{
    public ?string $participant = 'member:ana';

    public bool $gameMaster = false;

    #[Override]
    public function participant(ResourceReference $table): ?ResourceReference
    {
        return 'table:7' === $table->toString() && null !== $this->participant ? ResourceReference::fromString($this->participant) : null;
    }

    #[Override]
    public function isGameMaster(ResourceReference $table): bool
    {
        return 'table:7' === $table->toString() && $this->gameMaster;
    }
}
