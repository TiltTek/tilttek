<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use Phil\Resource\ResourceReference;
use Tilttek\Guilds\Contract\GuildStore;
use Tilttek\Quests\Contract\TeamMembership;

/**
 * The guilds as the teams of `tilttek/quests`: a member joins a team quest under a guild they belong to, read from the
 * store, never from a team the browser named. The guild provides the circle of members; the quest stays in
 * `tilttek/quests`.
 */
final readonly class GuildTeamMembership implements TeamMembership
{
    public function __construct(
        private GuildStore $store,
    ) {
    }

    public function isMember(ResourceReference $member, ResourceReference $team): bool
    {
        return $this->store->membership($team->toString(), $member->toString()) instanceof Membership;
    }
}
