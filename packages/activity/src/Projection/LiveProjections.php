<?php

declare(strict_types=1);

namespace Tilttek\Activity\Projection;

use Override;
use Tilttek\Activity\Entry;
use Tilttek\Activity\JournalObserver;

/**
 * Keeps the projections up to date as the journal is written: each new entry catches up every projection whose
 * filter reads it, in the same process. If a projection fails, the entry stays recorded and the projection's
 * checkpoint stays before it, so the next catch-up hands it again.
 */
final readonly class LiveProjections implements JournalObserver
{
    public function __construct(
        private Projector $projector,
        private Projections $projections,
    ) {
    }

    #[Override]
    public function appended(Entry $entry): void
    {
        foreach ($this->projections->all() as $projection) {
            if ($projection->filter()->matches($entry->activity)) {
                $this->projector->catchUp($projection);
            }
        }
    }
}
