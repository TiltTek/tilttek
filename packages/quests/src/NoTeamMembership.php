<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Phil\Resource\ResourceReference;
use Tilttek\Quests\Contract\TeamMembership;

/**
 * The membership of an application that has not told its teams: no member belongs to any team, so every team quest is
 * refused until the application gives its own `TeamMembership`.
 */
final readonly class NoTeamMembership implements TeamMembership
{
    public function isMember(ResourceReference $member, ResourceReference $team): bool
    {
        return false;
    }
}
