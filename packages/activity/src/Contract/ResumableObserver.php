<?php

declare(strict_types=1);

namespace Tilttek\Activity\Contract;

use Tilttek\Activity\Entry;
use Tilttek\Activity\JournalObserver;

/**
 * An observer that keeps a checkpoint, told again of an entry the journal already held: when the same gesture is
 * recorded, or the same cancellation given, again under its key — a request repeated after a failure —, the journal
 * hands the entry it found to `resume()`. The observer takes it up if it has not processed it yet — its checkpoint is
 * before the entry's position —, and does nothing otherwise; so a failure after the entry was written is made up by
 * the repeated request.
 */
interface ResumableObserver extends JournalObserver
{
    public function resume(Entry $entry): void;
}
