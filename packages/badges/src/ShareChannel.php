<?php

declare(strict_types=1);

namespace Tilttek\Badges;

/**
 * Where a member shares an achievement — an image, a link, a post on the Fediverse —, only when they ask for it.
 */
interface ShareChannel
{
    /**
     * Shares the award and returns what the channel made of it: a link, the address of a post.
     */
    public function share(Award $award, Badge $badge): string;
}
