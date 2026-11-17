<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Tilttek\Quests\Contract\TeamMembership;

/**
 * The teams of a test: every member of every team when no team is listed, or the members listed by team.
 */
final readonly class Teams implements TeamMembership
{
    /**
     * @param ?array<string, list<string>> $members the members of each team, by reference, or null for everyone
     */
    public function __construct(
        private ?array $members = null,
    ) {
    }

    public function isMember(ResourceReference $member, ResourceReference $team): bool
    {
        return null === $this->members || \in_array($member->toString(), $this->members[$team->toString()] ?? [], true);
    }
}
