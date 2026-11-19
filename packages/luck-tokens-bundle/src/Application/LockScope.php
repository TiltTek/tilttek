<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Application;

/**
 * What one lock of `LockedLuckTokens` covers.
 */
enum LockScope: string
{
    /**
     * The tokens of one holder, wherever they are held: two operations on the same holder wait for one another, those
     * on other holders of the table do not.
     */
    case Holder = 'holder';

    /**
     * A whole table: every operation on its ledger waits for the one before, for a table whose players spend all at
     * once.
     */
    case Table = 'table';
}
