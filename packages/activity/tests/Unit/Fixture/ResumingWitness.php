<?php

declare(strict_types=1);

namespace Tilttek\Activity\Tests\Unit\Fixture;

use Override;
use Tilttek\Activity\Contract\ResumableObserver;
use Tilttek\Activity\Entry;

/**
 * An observer that keeps a checkpoint: it writes what it is told, and what it is told again, in a list shared with
 * other witnesses.
 */
final readonly class ResumingWitness implements ResumableObserver
{
    public function __construct(
        private string $name,
        private Told $told,
    ) {
    }

    #[Override]
    public function appended(Entry $entry): void
    {
        $this->told->lines[] = $this->name.':'.$entry->kind->value.':'.$entry->position;
    }

    #[Override]
    public function resume(Entry $entry): void
    {
        $this->told->lines[] = $this->name.':again:'.$entry->kind->value.':'.$entry->position;
    }
}
