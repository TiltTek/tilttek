<?php

declare(strict_types=1);

namespace Tilttek\Quests\Contract;

use Phil\Resource\ResourceReference;

/**
 * Whether a member belongs to a team, as the application knows its teams: `Quests::accept()` asks it before a member
 * joins a team quest under a team, and refuses the quest when the answer is no.
 *
 * An implementation answers from the server's own records — never from a team the browser named — and keeps no state
 * between two calls.
 */
interface TeamMembership
{
    public function isMember(ResourceReference $member, ResourceReference $team): bool;
}
