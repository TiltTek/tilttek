<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Activity;

use Override;
use Tilttek\Activity\Entry;
use Tilttek\Activity\JournalObserver;
use Tilttek\Bingo\Bingo;

/**
 * Ticks the bingo cards as the activity journal records the members' gestures, and unticks them when a gesture is
 * cancelled: register it among the observers of the `Journal`.
 */
final readonly class BingoActivityObserver implements JournalObserver
{
    public function __construct(
        private Bingo $bingo,
    ) {
    }

    #[Override]
    public function appended(Entry $entry): void
    {
        if ($entry->isCancellation()) {
            $this->bingo->cancel($entry->activity);

            return;
        }

        $this->bingo->record($entry->activity);
    }
}
