<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

/**
 * Where a challenge stands. It opens with its prize held; it ends won — the prize paid to the winner —, or cancelled,
 * expired or removed — the prize given back to the creator. A moderator freezes an open challenge, which keeps its
 * prize held and stops every move until it is unfrozen or removed.
 *
 * ```text
 * open ──► won
 *  │ ▲  └─► cancelled | expired
 *  ▼ │
 * frozen ──► removed ◄── open
 * ```
 */
enum ChallengeState: string
{
    case Open = 'open';
    case Frozen = 'frozen';
    case Won = 'won';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Removed = 'removed';

    /**
     * Whether the prize is settled: paid to the winner, or given back to the creator.
     */
    public function isSettled(): bool
    {
        return self::Open !== $this && self::Frozen !== $this;
    }
}
