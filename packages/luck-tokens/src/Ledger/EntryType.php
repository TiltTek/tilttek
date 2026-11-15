<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Ledger;

/**
 * What an entry of the ledger records.
 */
enum EntryType: string
{
    case Granted = 'granted';
    case Spent = 'spent';
    case TransferredOut = 'transferred-out';
    case TransferredIn = 'transferred-in';

    /**
     * A holder's tokens set at the start of a session, by carry-over and refresh.
     */
    case Refreshed = 'refreshed';

    /**
     * A session started: the entry that marks it, with no token, before the refreshes it brings.
     */
    case SessionStarted = 'session-started';
}
