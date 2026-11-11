<?php

declare(strict_types=1);

namespace Tilttek\Activity;

/**
 * Told of each new entry of the journal, right after it is written: an activity recorded, or cancelled, for the first
 * time. A duplicate is not told. What an observer does is done in the same process; a reader that must survive a
 * failure keeps a checkpoint instead, as a projection does.
 */
interface JournalObserver
{
    public function appended(Entry $entry): void;
}
