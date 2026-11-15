<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Report;

/**
 * The moderation of the site: where the reports go, and who moderates. The library freezes, removes and withdraws on
 * a moderator's word; the site's queue, its rules and its notices stay the site's.
 */
interface Moderation
{
    /**
     * Sends a member's report to the moderators of the site.
     */
    public function report(Report $report): void;

    /**
     * Whether the member moderates the challenges of the site.
     */
    public function mayModerate(ResourceReference $member): bool;
}
