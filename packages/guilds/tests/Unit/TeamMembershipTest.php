<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\GuildTeamMembership;
use Tilttek\Guilds\Store\InMemoryGuildStore;
use Tilttek\Leaderboards\Circle;

#[CoversClass(GuildTeamMembership::class)]
final class TeamMembershipTest extends TestCase
{
    public function testAGuildIsTheTeamOfAQuestAndTheCircleOfALeaderboard(): void
    {
        $store = new InMemoryGuildStore();
        $guilds = new Guilds($store, new MockClock('2026-11-21 10:00:00', 'UTC'));
        $guild = ResourceReference::of('guild', '44');
        $guilds->found($guild, 'Lanterns', ResourceReference::of('member', '1'));
        $guilds->join($guild, ResourceReference::of('member', '2'));

        $membership = new GuildTeamMembership($store);

        self::assertTrue($membership->isMember(ResourceReference::of('member', '2'), $guild));
        self::assertFalse($membership->isMember(ResourceReference::of('member', '3'), $guild));
        self::assertFalse($membership->isMember(ResourceReference::of('member', '2'), ResourceReference::of('guild', '45')));

        $circle = new Circle(...$guilds->references($guild));
        self::assertSame(['member:1', 'member:2'], $circle->members);
    }
}
