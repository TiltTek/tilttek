<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

/**
 * What rewarding a referral did: the referral as settled, the points each side holds from it, and whether this call
 * wrote a movement of points or found them all written already.
 */
final readonly class Reward
{
    public function __construct(
        public Referral $referral,
        public int $referrerPoints,
        public int $newcomerPoints,
        public bool $new,
    ) {
    }
}
