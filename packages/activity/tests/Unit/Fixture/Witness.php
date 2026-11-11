<?php

declare(strict_types=1);

namespace Tilttek\Activity\Tests\Unit\Fixture;

use Override;
use Tilttek\Activity\Entry;
use Tilttek\Activity\JournalObserver;

/**
 * An observer that writes what it is told in a list shared with other witnesses.
 */
final readonly class Witness implements JournalObserver
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
}
