<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Activity;

use Override;
use Tilttek\Activity\Contract\ResumableObserver;
use Tilttek\Activity\Entry;
use Tilttek\Bingo\Bingo;

/**
 * Ticks the bingo cards as the activity journal records the members' gestures, and unticks them when a gesture is
 * cancelled: register it among the observers of the `Journal`. The same gesture recorded again, or the same
 * cancellation given again, hands it the entry once more: it records or cancels it again, which changes nothing a first
 * pass made and dispatches the events a listener had failed on.
 */
final readonly class BingoActivityObserver implements ResumableObserver
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

    #[Override]
    public function resume(Entry $entry): void
    {
        $this->appended($entry);
    }
}
