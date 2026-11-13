<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Journal;

/**
 * What an entry of a scene's journal records.
 */
enum EntryKind: string
{
    /** A hidden roll was made: its commitment is published, its opening stays sealed. */
    case Committed = 'committed';

    /** A hidden roll is revealed: its opening is published, which anyone checks against its commitment. */
    case Revealed = 'revealed';

    /** The scene ended: it takes no more rolls; the rolls it did not reveal stay withheld, their commitments kept. */
    case Closed = 'closed';
}
