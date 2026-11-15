<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

/**
 * Where a bounty stands. It opens with its points held; it ends awarded — the awards paid, the rest given back to its
 * sponsor —, expired — nothing awarded, everything given back —, or cancelled by its sponsor before any claim.
 *
 * ```text
 * open ──► awarded
 *   └───► expired | cancelled
 * ```
 */
enum BountyState: string
{
    case Open = 'open';
    case Awarded = 'awarded';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Whether the points are settled: paid to the winners, or given back to the sponsor.
     */
    public function isSettled(): bool
    {
        return self::Open !== $this;
    }
}
