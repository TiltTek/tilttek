<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

/**
 * Why a sponsor offers a bounty, chosen from a list and shown with it.
 */
enum BountyReason: string
{
    /** The subject deserves more attention. */
    case Attention = 'attention';
    /** An answer with more detail is wanted. */
    case Detail = 'detail';
    /** An answer drawn from reliable sources is wanted. */
    case Sources = 'sources';
    /** An answer given already deserves a reward. */
    case Reward = 'reward';
    /** A creative answer is wanted: a drawing, a story, a build. */
    case Creativity = 'creativity';
    case Other = 'other';
}
