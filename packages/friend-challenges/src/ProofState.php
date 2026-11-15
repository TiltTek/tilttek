<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

/**
 * Where a proof stands: waiting for its review, accepted — its participant wins —, rejected, or removed by a
 * moderator.
 */
enum ProofState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Removed = 'removed';
}
