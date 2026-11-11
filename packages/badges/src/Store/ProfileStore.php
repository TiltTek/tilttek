<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use Phil\Resource\ResourceReference;
use Tilttek\Badges\Profile;

/**
 * Where the members' profiles are kept: the badges each shows, their showcase and their title.
 */
interface ProfileStore
{
    /**
     * The member's profile; an empty one when they never showed a badge.
     */
    public function get(ResourceReference $member): Profile;

    /**
     * Keeps the profile; an empty profile is removed.
     */
    public function save(Profile $profile): void;

    /**
     * Removes the member's profile; false when there was none.
     */
    public function erase(ResourceReference $member): bool;
}
