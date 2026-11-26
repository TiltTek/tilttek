<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Guilds\Admission;
use Tilttek\Guilds\Change;
use Tilttek\Guilds\Exception\GuildRefused;
use Tilttek\Guilds\Exception\UnknownGuild;
use Tilttek\Guilds\Guild;
use Tilttek\Guilds\GuildChanged;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\JoinRequest;
use Tilttek\Guilds\Limits;
use Tilttek\Guilds\Membership;
use Tilttek\Guilds\Metadata;
use Tilttek\Guilds\Refusal;
use Tilttek\Guilds\Role;
use Tilttek\Guilds\Store\InMemoryGuildStore;
use Tilttek\Guilds\Tests\Unit\Fixture\Recorder;

#[CoversClass(Guilds::class)]
#[CoversClass(InMemoryGuildStore::class)]
#[CoversClass(Membership::class)]
#[CoversClass(JoinRequest::class)]
#[CoversClass(GuildChanged::class)]
final class GuildsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryGuildStore $store;

    private Recorder $recorder;

    private Guilds $guilds;

    private ResourceReference $guild;

    private ResourceReference $founder;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-21 10:00:00', 'UTC');
        $this->store = new InMemoryGuildStore();
        $this->recorder = new Recorder();
        $this->guilds = new Guilds($this->store, $this->clock, new Limits(), [$this->recorder]);
        $this->guild = ResourceReference::of('guild', '44');
        $this->founder = $this->member('1');
    }

    public function testAFounderFoundsAGuildAndIsItsFirstMember(): void
    {
        $guild = $this->guilds->found($this->guild, 'The Lantern Bearers', $this->founder, Admission::OnRequest, 10, new Metadata(['motto' => 'Light']));

        self::assertSame(['The Lantern Bearers', Admission::OnRequest, 10, 'Light'], [$guild->name, $guild->admission, $guild->capacity, $guild->metadata->get('motto')]);
        self::assertSame('2026-11-21 10:00:00', $guild->foundedAt->format('Y-m-d H:i:s'));
        self::assertSame($guild, $this->guilds->guild($this->guild));
        self::assertSame(Role::Founder, $this->guilds->membership($this->guild, $this->founder)?->role);
        self::assertSame(['guild:44 founded member:1 -'], $this->recorder->summary());
        self::assertSame('2026-11-21 10:00:00', $this->recorder->changes[0]->at->format('Y-m-d H:i:s'));
    }

    public function testAGuildIsOpenWithFiftyPlacesByDefault(): void
    {
        $guild = $this->guilds->found($this->guild, 'Lanterns', $this->founder);

        self::assertSame([Admission::Open, 50, []], [$guild->admission, $guild->capacity, $guild->metadata->entries]);
    }

    public function testAReferenceIsFoundedOnce(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);

        $this->assertRefused(Refusal::AlreadyFounded, fn (): Guild => $this->guilds->found($this->guild, 'Other', $this->member('2')));
    }

    public function testAnUnknownGuildIsReported(): void
    {
        $this->expectException(UnknownGuild::class);
        $this->expectExceptionMessageIsOrContains('No guild is known as "guild:44".');

        $this->guilds->guild($this->guild);
    }

    public function testAMemberEntersAnOpenGuildAtOnce(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->clock->modify('+1 hour');

        $joined = $this->guilds->join($this->guild, $this->member('2'));

        self::assertInstanceOf(Membership::class, $joined);
        self::assertSame([Role::Member, '2026-11-21 11:00:00'], [$joined->role, $joined->since->format('Y-m-d H:i:s')]);
        self::assertTrue($this->guilds->isMember($this->member('2'), $this->guild));
        self::assertFalse($this->guilds->isMember($this->member('3'), $this->guild));
        self::assertSame('guild:44 joined member:2 -', $this->recorder->summary()[1]);
        $this->assertRefused(Refusal::AlreadyMember, fn (): Membership|JoinRequest => $this->guilds->join($this->guild, $this->member('2')));
        $this->assertRefused(Refusal::AlreadyMember, fn (): Membership|JoinRequest => $this->guilds->join($this->guild, $this->founder));
    }

    public function testARequestIsApprovedOrDeclinedByAnAdministrator(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder, Admission::OnRequest);
        $requested = $this->guilds->join($this->guild, $this->member('2'));
        $this->clock->modify('+1 minute');
        $this->guilds->join($this->guild, $this->member('3'));

        self::assertInstanceOf(JoinRequest::class, $requested);
        self::assertSame('2026-11-21 10:00:00', $requested->requestedAt->format('Y-m-d H:i:s'));
        self::assertFalse($this->guilds->isMember($this->member('2'), $this->guild));
        self::assertSame(['member:2', 'member:3'], array_map(static fn (JoinRequest $request): string => $request->member->toString(), $this->guilds->requests($this->guild, $this->founder)));
        $this->assertRefused(Refusal::AlreadyRequested, fn (): Membership|JoinRequest => $this->guilds->join($this->guild, $this->member('2')));

        $this->clock->modify('+1 minute');
        $approved = $this->guilds->approve($this->guild, $this->member('2'), $this->founder);
        $this->assertRefused(Refusal::NotAllowed, fn (): array => $this->guilds->requests($this->guild, $this->member('2')));
        $this->assertRefused(Refusal::NotAllowed, fn (): Membership => $this->guilds->approve($this->guild, $this->member('3'), $this->member('2')));
        $this->assertRefused(Refusal::NotAllowed, fn () => $this->guilds->decline($this->guild, $this->member('3'), $this->member('2')));
        $this->guilds->decline($this->guild, $this->member('3'), $this->founder);

        self::assertSame([Role::Member, '2026-11-21 10:02:00'], [$approved->role, $approved->since->format('Y-m-d H:i:s')]);
        self::assertSame([], $this->guilds->requests($this->guild, $this->founder));
        self::assertFalse($this->guilds->isMember($this->member('3'), $this->guild));
        self::assertSame([
            'guild:44 founded member:1 -',
            'guild:44 requested member:2 -',
            'guild:44 requested member:3 -',
            'guild:44 approved member:2 member:1',
            'guild:44 declined member:3 member:1',
        ], $this->recorder->summary());
        $this->assertRefused(Refusal::NoRequest, fn (): Membership => $this->guilds->approve($this->guild, $this->member('3'), $this->founder));
        $this->assertRefused(Refusal::NoRequest, fn () => $this->guilds->decline($this->guild, $this->member('3'), $this->founder));
        $this->assertRefused(Refusal::NotMember, fn (): array => $this->guilds->requests($this->guild, $this->member('3')));
    }

    public function testAMemberWithdrawsTheirRequest(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder, Admission::OnRequest);
        $this->guilds->join($this->guild, $this->member('2'));

        $this->guilds->withdraw($this->guild, $this->member('2'));

        self::assertSame([], $this->guilds->requests($this->guild, $this->founder));
        self::assertSame('guild:44 withdrawn member:2 -', $this->recorder->summary()[2]);
        $this->assertRefused(Refusal::NoRequest, fn () => $this->guilds->withdraw($this->guild, $this->member('2')));
    }

    public function testAFullGuildRefusesNewMembersAndApprovals(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder, Admission::Open, 2);
        $this->guilds->join($this->guild, $this->member('2'));

        $this->assertRefused(Refusal::Full, fn (): Membership|JoinRequest => $this->guilds->join($this->guild, $this->member('3')));

        $this->guilds->update($this->guild, $this->founder, admission: Admission::OnRequest, capacity: 3);
        $this->guilds->join($this->guild, $this->member('3'));
        $this->guilds->join($this->guild, $this->member('4'));
        $this->guilds->approve($this->guild, $this->member('3'), $this->founder);

        $this->assertRefused(Refusal::Full, fn (): Membership => $this->guilds->approve($this->guild, $this->member('4'), $this->founder));
        self::assertCount(3, $this->guilds->members($this->guild));
    }

    public function testTheWaitingRequestsOfAGuildAreBounded(): void
    {
        $guilds = new Guilds($this->store, $this->clock, new Limits(requestsPerGuild: 1));
        $guilds->found($this->guild, 'Lanterns', $this->founder, Admission::OnRequest);
        $guilds->join($this->guild, $this->member('2'));

        $this->assertRefused(Refusal::TooManyRequests, fn (): Membership|JoinRequest => $guilds->join($this->guild, $this->member('3')));
    }

    public function testTheGuildsOfAMemberAreBoundedRequestsIncluded(): void
    {
        $guilds = new Guilds($this->store, $this->clock, new Limits(guildsPerMember: 2));
        $guilds->found($this->guild, 'Lanterns', $this->founder);
        $guilds->found(ResourceReference::of('guild', '45'), 'Torches', $this->member('2'), Admission::OnRequest);
        $guilds->join(ResourceReference::of('guild', '45'), $this->founder);

        $this->assertRefused(Refusal::TooManyGuilds, fn (): Guild => $guilds->found(ResourceReference::of('guild', '46'), 'Candles', $this->founder));
        $guilds->found(ResourceReference::of('guild', '46'), 'Candles', $this->member('3'));
        $this->assertRefused(Refusal::TooManyGuilds, fn (): Membership|JoinRequest => $guilds->join(ResourceReference::of('guild', '46'), $this->founder));
    }

    public function testAMemberLeavesButTheFounderHandsTheGuildOverFirst(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->guilds->join($this->guild, $this->member('2'));

        $this->guilds->leave($this->guild, $this->member('2'));

        self::assertFalse($this->guilds->isMember($this->member('2'), $this->guild));
        self::assertSame('guild:44 left member:2 -', $this->recorder->summary()[2]);
        $this->assertRefused(Refusal::FounderStays, fn () => $this->guilds->leave($this->guild, $this->founder));
        $this->assertRefused(Refusal::NotMember, fn () => $this->guilds->leave($this->guild, $this->member('2')));
    }

    public function testTheFounderRemovesAnyoneAndAnAdministratorRemovesMembers(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);

        foreach (['2', '3', '4', '5'] as $id) {
            $this->guilds->join($this->guild, $this->member($id));
        }

        $this->guilds->promote($this->guild, $this->member('2'), $this->founder);
        $this->guilds->promote($this->guild, $this->member('3'), $this->founder);

        $this->guilds->remove($this->guild, $this->member('4'), $this->member('2'));
        $this->assertRefused(Refusal::NotAllowed, fn () => $this->guilds->remove($this->guild, $this->member('3'), $this->member('2')));
        $this->assertRefused(Refusal::NotAllowed, fn () => $this->guilds->remove($this->guild, $this->founder, $this->member('2')));
        $this->assertRefused(Refusal::NotAllowed, fn () => $this->guilds->remove($this->guild, $this->member('2'), $this->member('5')));
        $this->assertRefused(Refusal::NotAllowed, fn () => $this->guilds->remove($this->guild, $this->founder, $this->founder));
        $this->assertRefused(Refusal::NotMember, fn () => $this->guilds->remove($this->guild, $this->member('4'), $this->founder));
        $this->assertRefused(Refusal::NotMember, fn () => $this->guilds->remove($this->guild, $this->member('5'), $this->member('4')));
        $this->guilds->remove($this->guild, $this->member('3'), $this->founder);

        self::assertSame(['member:1', 'member:2', 'member:5'], array_map(static fn (Membership $membership): string => $membership->member->toString(), $this->guilds->members($this->guild)));
        self::assertSame(['guild:44 removed member:4 member:2', 'guild:44 removed member:3 member:1'], \array_slice($this->recorder->summary(), -2));
    }

    public function testTheFounderPromotesAndDemotes(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->guilds->join($this->guild, $this->member('2'));
        $this->guilds->join($this->guild, $this->member('3'));

        $promoted = $this->guilds->promote($this->guild, $this->member('2'), $this->founder);

        self::assertSame(Role::Administrator, $promoted->role);
        self::assertSame(Role::Administrator, $this->guilds->membership($this->guild, $this->member('2'))?->role);
        $this->assertRefused(Refusal::NotAllowed, fn (): Membership => $this->guilds->promote($this->guild, $this->member('3'), $this->member('2')));
        $this->assertRefused(Refusal::NotAllowed, fn (): Membership => $this->guilds->promote($this->guild, $this->member('2'), $this->founder));
        $this->assertRefused(Refusal::NotAllowed, fn (): Membership => $this->guilds->demote($this->guild, $this->member('3'), $this->founder));
        $this->assertRefused(Refusal::NotAllowed, fn (): Membership => $this->guilds->demote($this->guild, $this->founder, $this->founder));

        $demoted = $this->guilds->demote($this->guild, $this->member('2'), $this->founder);

        self::assertSame(Role::Member, $demoted->role);
        self::assertSame($promoted->since, $demoted->since);
        self::assertSame(Role::Member, $this->guilds->membership($this->guild, $this->member('2'))?->role);
        self::assertSame(['guild:44 promoted member:2 member:1', 'guild:44 demoted member:2 member:1'], \array_slice($this->recorder->summary(), -2));
    }

    public function testTheFounderHandsTheGuildOverAndStaysAnAdministrator(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->guilds->join($this->guild, $this->member('2'));

        $this->assertRefused(Refusal::NotAllowed, fn () => $this->guilds->transfer($this->guild, $this->founder, $this->founder));
        $this->assertRefused(Refusal::NotAllowed, fn () => $this->guilds->transfer($this->guild, $this->member('2'), $this->member('2')));
        $this->assertRefused(Refusal::NotMember, fn () => $this->guilds->transfer($this->guild, $this->member('3'), $this->founder));

        $this->guilds->transfer($this->guild, $this->member('2'), $this->founder);

        self::assertSame(Role::Founder, $this->guilds->membership($this->guild, $this->member('2'))?->role);
        self::assertSame(Role::Administrator, $this->guilds->membership($this->guild, $this->founder)?->role);
        self::assertSame('guild:44 transferred member:2 member:1', $this->recorder->summary()[2]);
        $this->guilds->leave($this->guild, $this->founder);
        self::assertSame(['member:2'], array_map(static fn (ResourceReference $member): string => $member->toString(), $this->guilds->references($this->guild)));
    }

    public function testAnAdministratorUpdatesTheGuildWithinItsMembers(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder, Admission::Open, 3);
        $this->guilds->join($this->guild, $this->member('2'));
        $this->guilds->join($this->guild, $this->member('3'));
        $this->guilds->promote($this->guild, $this->member('2'), $this->founder);

        $updated = $this->guilds->update($this->guild, $this->member('2'), 'Lantern Bearers', Admission::OnRequest, 3, new Metadata(['lang' => 'fr']));

        self::assertSame(['Lantern Bearers', Admission::OnRequest, 3, 'fr'], [$updated->name, $updated->admission, $updated->capacity, $updated->metadata->get('lang')]);
        self::assertSame($updated, $this->guilds->guild($this->guild));
        self::assertSame('guild:44 updated - member:2', $this->recorder->summary()[4]);
        $this->assertRefused(Refusal::BelowMembers, fn (): Guild => $this->guilds->update($this->guild, $this->founder, capacity: 2));
        $this->assertRefused(Refusal::NotAllowed, fn (): Guild => $this->guilds->update($this->guild, $this->member('3'), 'Mine'));
        self::assertSame('Lantern Bearers', $this->guilds->guild($this->guild)->name);
    }

    public function testTheFounderDissolvesTheGuild(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder, Admission::OnRequest);
        $this->guilds->join($this->guild, $this->member('2'));
        $this->guilds->approve($this->guild, $this->member('2'), $this->founder);
        $this->guilds->join($this->guild, $this->member('3'));
        $this->guilds->promote($this->guild, $this->member('2'), $this->founder);

        $this->assertRefused(Refusal::NotAllowed, fn () => $this->guilds->dissolve($this->guild, $this->member('2')));
        $this->guilds->dissolve($this->guild, $this->founder);

        self::assertNull($this->store->guild('guild:44'));
        self::assertSame([], $this->guilds->guildsOf($this->founder));
        self::assertSame([], $this->store->requestsOf('member:3'));
        self::assertSame('guild:44 dissolved - member:1', $this->recorder->summary()[5]);
        $this->expectException(UnknownGuild::class);
        $this->guilds->members($this->guild);
    }

    public function testMembersAreReadByRoleThenByArrival(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);

        foreach (['5', '3', '4', '2'] as $id) {
            $this->clock->modify('+1 minute');
            $this->guilds->join($this->guild, $this->member($id));
        }

        $this->guilds->promote($this->guild, $this->member('4'), $this->founder);
        $this->guilds->promote($this->guild, $this->member('3'), $this->founder);

        self::assertSame(
            ['member:1 founder', 'member:3 administrator', 'member:4 administrator', 'member:5 member', 'member:2 member'],
            array_map(static fn (Membership $membership): string => $membership->member->toString().' '.$membership->role->value, $this->guilds->members($this->guild)),
        );
        self::assertSame(['member:1', 'member:5', 'member:3', 'member:4', 'member:2'], array_map(static fn (ResourceReference $member): string => $member->toString(), $this->guilds->references($this->guild)));
    }

    public function testMembersArrivedTogetherAreReadInTheOrderOfTheirReferences(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->guilds->join($this->guild, $this->member('3'));
        $this->guilds->join($this->guild, $this->member('2'));

        self::assertSame(['member:1', 'member:2', 'member:3'], array_map(static fn (ResourceReference $member): string => $member->toString(), $this->guilds->references($this->guild)));
    }

    public function testMembersAreReadWhateverRolesTheStoreHolds(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->assertMembers(['member:1 founder']);

        $this->store->removeMembership('guild:44', 'member:1');
        $this->assertMembers([]);

        $since = $this->clock->now();
        $this->store->saveMembership(new Membership($this->guild, $this->member('3'), Role::Member, $since));
        $this->store->saveMembership(new Membership($this->guild, $this->member('2'), Role::Administrator, $since));
        $this->assertMembers(['member:2 administrator', 'member:3 member']);

        $this->store->saveMembership(new Membership($this->guild, $this->member('4'), Role::Founder, $since));
        $this->store->saveMembership(new Membership($this->guild, $this->member('1'), Role::Founder, $since));
        $this->assertMembers(['member:1 founder', 'member:4 founder', 'member:2 administrator', 'member:3 member']);
    }

    public function testRequestsArrivedTogetherAreOrderedByGuildThenMember(): void
    {
        $at = $this->clock->now();

        foreach ([['b', '2'], ['a', '3'], ['ab', '1'], ['a', '1'], ['b', '1'], ['a', '2'], ['ab', '2']] as [$guild, $member]) {
            $this->store->saveRequest(new JoinRequest(ResourceReference::of('guild', $guild), $this->member($member), $at));
        }

        $describe = static fn (array $requests): array => array_map(static fn (JoinRequest $request): string => $request->guild->toString().' '.$request->member->toString(), $requests);

        self::assertSame(['guild:a member:1', 'guild:a member:2', 'guild:a member:3'], $describe($this->store->requests('guild:a')));
        self::assertSame(['guild:ab member:1', 'guild:ab member:2'], $describe($this->store->requests('guild:ab')));
        self::assertSame(['guild:a member:1', 'guild:ab member:1', 'guild:b member:1'], $describe($this->store->requestsOf('member:1')));
        self::assertSame(['guild:a member:2', 'guild:ab member:2', 'guild:b member:2'], $describe($this->store->requestsOf('member:2')));
    }

    public function testErasingAMemberReachesEveryGuildTheyBelongTo(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->guilds->found(ResourceReference::of('guild', '45'), 'Torches', $this->founder);
        $this->guilds->found(ResourceReference::of('guild', '46'), 'Embers', $this->member('9'));

        foreach (['44', '45', '46'] as $guild) {
            $this->clock->modify('+1 minute');
            $this->guilds->join(ResourceReference::of('guild', $guild), $this->member('2'));
        }

        $this->guilds->erase($this->member('2'));

        self::assertSame([], $this->guilds->guildsOf($this->member('2')));
        self::assertSame(['guild:44 left member:2 -', 'guild:45 left member:2 -', 'guild:46 left member:2 -'], \array_slice($this->recorder->summary(), -3));

        $this->guilds->erase($this->founder);

        self::assertNull($this->store->guild('guild:44'));
        self::assertNull($this->store->guild('guild:45'));
        self::assertSame(['guild:44 dissolved - -', 'guild:45 dissolved - -'], \array_slice($this->recorder->summary(), -2));
    }

    public function testTheReferencesOfAnUnknownGuildAreRefused(): void
    {
        $this->expectException(UnknownGuild::class);

        $this->guilds->references($this->guild);
    }

    public function testTheGuildsOfAMemberAreReadByArrival(): void
    {
        $this->guilds->found(ResourceReference::of('guild', 'b'), 'B', $this->member('9'));
        $this->guilds->found(ResourceReference::of('guild', 'a'), 'A', $this->member('9'));
        $this->guilds->join(ResourceReference::of('guild', 'b'), $this->founder);

        $this->clock->modify('+1 minute');
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->clock->modify('-2 minutes');
        $this->guilds->join(ResourceReference::of('guild', 'a'), $this->founder);

        self::assertSame(['guild:a', 'guild:b', 'guild:44'], array_map(static fn (Membership $membership): string => $membership->guild->toString(), $this->guilds->guildsOf($this->founder)));
    }

    public function testActsOnAnUnknownGuildAreRefused(): void
    {
        $this->assertUnknown(fn (): Membership|JoinRequest => $this->guilds->join($this->guild, $this->member('2')));
        $this->assertUnknown(fn (): Membership => $this->guilds->approve($this->guild, $this->member('2'), $this->founder));
        $this->assertUnknown(fn () => $this->guilds->leave($this->guild, $this->member('2')));
        $this->assertUnknown(fn () => $this->guilds->decline($this->guild, $this->member('2'), $this->founder));
        $this->assertUnknown(fn (): Guild => $this->guilds->update($this->guild, $this->founder, 'X'));
    }

    public function testErasingAMemberRemovesTheirRequestsAndMemberships(): void
    {
        $other = ResourceReference::of('guild', '45');
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->guilds->found($other, 'Torches', $this->member('9'), Admission::OnRequest);
        $this->guilds->join($this->guild, $this->member('2'));
        $this->guilds->join($other, $this->member('2'));

        $this->guilds->erase($this->member('2'));

        self::assertSame([], $this->guilds->guildsOf($this->member('2')));
        self::assertSame([], $this->store->requestsOf('member:2'));
        self::assertSame(['guild:45 withdrawn member:2 -', 'guild:44 left member:2 -'], \array_slice($this->recorder->summary(), -2));
    }

    public function testErasingAFounderHandsTheGuildToTheEarliestAdministrator(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);

        foreach (['2', '3', '4'] as $id) {
            $this->clock->modify('+1 minute');
            $this->guilds->join($this->guild, $this->member($id));
        }

        $this->guilds->promote($this->guild, $this->member('4'), $this->founder);
        $this->guilds->promote($this->guild, $this->member('3'), $this->founder);

        $this->guilds->erase($this->founder);

        self::assertSame(Role::Founder, $this->guilds->membership($this->guild, $this->member('3'))?->role);
        self::assertSame(Role::Administrator, $this->guilds->membership($this->guild, $this->member('4'))?->role);
        self::assertSame('guild:44 transferred member:3 -', $this->recorder->summary()[\count($this->recorder->changes) - 1]);
    }

    public function testErasingAFounderWithoutAdministratorHandsTheGuildToTheEarliestMember(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->clock->modify('+1 minute');
        $this->guilds->join($this->guild, $this->member('3'));
        $this->clock->modify('+1 minute');
        $this->guilds->join($this->guild, $this->member('2'));

        $this->guilds->erase($this->founder);

        self::assertSame(Role::Founder, $this->guilds->membership($this->guild, $this->member('3'))?->role);
        self::assertSame(Role::Member, $this->guilds->membership($this->guild, $this->member('2'))?->role);
    }

    public function testErasingTheOnlyMemberDissolvesTheGuild(): void
    {
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);

        $this->guilds->erase($this->founder);

        self::assertNull($this->store->guild('guild:44'));
        self::assertSame('guild:44 dissolved - -', $this->recorder->summary()[1]);
    }

    public function testAGuildWithoutObserverTellsNobody(): void
    {
        $guilds = new Guilds($this->store, $this->clock);
        $guilds->found($this->guild, 'Lanterns', $this->founder);

        self::assertSame([], $this->recorder->changes);
        self::assertSame(Change::Founded, Change::from('founded'));
    }

    /**
     * @param list<string> $expected
     */
    private function assertMembers(array $expected): void
    {
        self::assertSame($expected, array_map(static fn (Membership $membership): string => $membership->member->toString().' '.$membership->role->value, $this->guilds->members($this->guild)));
    }

    private function assertRefused(Refusal $refusal, callable $act): void
    {
        try {
            $act();
            self::fail(\sprintf('The act was not refused for "%s".', $refusal->value));
        } catch (GuildRefused $guildRefused) {
            self::assertSame($refusal, $guildRefused->refusal);
        }
    }

    private function assertUnknown(callable $act): void
    {
        try {
            $act();
            self::fail('The guild was not reported unknown.');
        } catch (UnknownGuild $unknownGuild) {
            self::assertSame('No guild is known as "guild:44".', $unknownGuild->getMessage());
        }
    }

    private function member(string $id): ResourceReference
    {
        return ResourceReference::of('member', $id);
    }
}
