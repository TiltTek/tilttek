<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Tilttek\Quests\Contract\TeamMembership;

/**
 * The teams of a test: every member belongs to every team.
 */
final readonly class Teams implements TeamMembership
{
    public function isMember(ResourceReference $member, ResourceReference $team): bool
    {
        return true;
    }
}
