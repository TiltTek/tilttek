<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens;

/**
 * When a kind of token comes back on its own.
 */
enum Refresh: string
{
    /**
     * Never: a holder has only what was granted and not spent.
     */
    case Never = 'never';

    /**
     * At the start of each session, up to the kind's refresh amount.
     */
    case EachSession = 'each-session';
}
