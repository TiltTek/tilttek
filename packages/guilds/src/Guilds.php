<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Guilds\Contract\GuildObserver;
use Tilttek\Guilds\Contract\GuildStore;
use Tilttek\Guilds\Exception\GuildRefused;
use Tilttek\Guilds\Exception\UnknownGuild;

/**
 * The guilds of the application: founded by a member, joined at once when they are open or by a request an
 * administrator approves, run by one founder and their administrators, bounded by a capacity.
 *
 * ```php
 * $guilds->found(ResourceReference::of('guild', '44'), 'The Lantern Bearers', $founder, Admission::OnRequest);
 * $guilds->join($guild, $member);           // a JoinRequest, since the guild is on request
 * $guilds->approve($guild, $member, $founder);
 * $guilds->references($guild);              // the members, for a team quest or a leaderboard's circle
 * ```
 *
 * Nobody is ever removed for being inactive: a member leaves, or the founder or an administrator removes them.
 */
final readonly class Guilds
{
    /**
     * @var list<GuildObserver>
     */
    private array $observers;

    /**
     * @param iterable<GuildObserver> $observers
     */
    public function __construct(
        private GuildStore $store,
        private ClockInterface $clock,
        private Limits $limits = new Limits(),
        iterable $observers = [],
    ) {
        $list = [];

        foreach ($observers as $observer) {
            $list[] = $observer;
        }

        $this->observers = $list;
    }

    /**
     * Founds a guild under the reference the application gives, its founder its first member.
     */
    public function found(ResourceReference $reference, string $name, ResourceReference $founder, Admission $admission = Admission::Open, int $capacity = Guild::DEFAULT_CAPACITY, Metadata $metadata = new Metadata()): Guild
    {
        $now = $this->clock->now();
        $guild = new Guild($reference, $name, $admission, $capacity, $metadata, $now);

        if ($this->store->guild($reference->toString()) instanceof Guild) {
            throw GuildRefused::because(Refusal::AlreadyFounded);
        }

        $this->checkRoomFor($founder);
        $this->store->saveGuild($guild);
        $this->store->saveMembership(new Membership($reference, $founder, Role::Founder, $now));
        $this->tell($reference, Change::Founded, $founder, null);

        return $guild;
    }

    /**
     * @throws UnknownGuild
     */
    public function guild(ResourceReference $guild): Guild
    {
        return $this->store->guild($guild->toString()) ?? throw UnknownGuild::named($guild->toString());
    }

    /**
     * The member enters an open guild at once, and asks to enter a guild on request.
     */
    public function join(ResourceReference $guild, ResourceReference $member): Membership|JoinRequest
    {
        $found = $this->guild($guild);

        if ($this->membership($guild, $member) instanceof Membership) {
            throw GuildRefused::because(Refusal::AlreadyMember);
        }

        if ($this->store->request($guild->toString(), $member->toString()) instanceof JoinRequest) {
            throw GuildRefused::because(Refusal::AlreadyRequested);
        }

        $this->checkRoomFor($member);

        if (Admission::Open === $found->admission) {
            return $this->enter($found, $member, Change::Joined, null);
        }

        if ($this->store->countRequests($guild->toString()) >= $this->limits->requestsPerGuild) {
            throw GuildRefused::because(Refusal::TooManyRequests);
        }

        $request = new JoinRequest($guild, $member, $this->clock->now());
        $this->store->saveRequest($request);
        $this->tell($guild, Change::Requested, $member, null);

        return $request;
    }

    /**
     * The member withdraws their waiting request.
     */
    public function withdraw(ResourceReference $guild, ResourceReference $member): void
    {
        $this->pendingRequest($guild, $member);
        $this->store->removeRequest($guild->toString(), $member->toString());
        $this->tell($guild, Change::Withdrawn, $member, null);
    }

    /**
     * The founder or an administrator approves a waiting request: the member enters the guild if it has room.
     */
    public function approve(ResourceReference $guild, ResourceReference $member, ResourceReference $by): Membership
    {
        $found = $this->guild($guild);
        $this->administrator($guild, $by);
        $this->pendingRequest($guild, $member);
        $this->store->removeRequest($guild->toString(), $member->toString());

        return $this->enter($found, $member, Change::Approved, $by);
    }

    /**
     * The founder or an administrator declines a waiting request.
     */
    public function decline(ResourceReference $guild, ResourceReference $member, ResourceReference $by): void
    {
        $this->administrator($guild, $by);
        $this->pendingRequest($guild, $member);
        $this->store->removeRequest($guild->toString(), $member->toString());
        $this->tell($guild, Change::Declined, $member, $by);
    }

    /**
     * The guild's waiting requests, the oldest first, for its founder and administrators only.
     *
     * @return list<JoinRequest>
     */
    public function requests(ResourceReference $guild, ResourceReference $by): array
    {
        $this->administrator($guild, $by);

        return $this->store->requests($guild->toString());
    }

    /**
     * The member leaves the guild; the founder hands it over first, or dissolves it.
     */
    public function leave(ResourceReference $guild, ResourceReference $member): void
    {
        if (Role::Founder === $this->member($guild, $member)->role) {
            throw GuildRefused::because(Refusal::FounderStays);
        }

        $this->store->removeMembership($guild->toString(), $member->toString());
        $this->tell($guild, Change::Left, $member, null);
    }

    /**
     * The founder removes any other member, an administrator removes members.
     */
    public function remove(ResourceReference $guild, ResourceReference $member, ResourceReference $by): void
    {
        $actor = $this->member($guild, $by);
        $target = $this->member($guild, $member);

        if (!$actor->role->canRemove($target->role)) {
            throw GuildRefused::because(Refusal::NotAllowed);
        }

        $this->store->removeMembership($guild->toString(), $member->toString());
        $this->tell($guild, Change::Removed, $member, $by);
    }

    /**
     * The founder makes a member an administrator.
     */
    public function promote(ResourceReference $guild, ResourceReference $member, ResourceReference $by): Membership
    {
        return $this->reassign($guild, $member, $by, Role::Member, Role::Administrator, Change::Promoted);
    }

    /**
     * The founder makes an administrator a member again.
     */
    public function demote(ResourceReference $guild, ResourceReference $member, ResourceReference $by): Membership
    {
        return $this->reassign($guild, $member, $by, Role::Administrator, Role::Member, Change::Demoted);
    }

    /**
     * The founder hands the guild to another member, and stays in it as an administrator.
     */
    public function transfer(ResourceReference $guild, ResourceReference $to, ResourceReference $by): void
    {
        $founder = $this->founder($guild, $by);
        $heir = $this->member($guild, $to);

        if ($heir->member->equals($founder->member)) {
            throw GuildRefused::because(Refusal::NotAllowed);
        }

        $this->store->saveMembership($founder->as(Role::Administrator));
        $this->store->saveMembership($heir->as(Role::Founder));
        $this->tell($guild, Change::Transferred, $to, $by);
    }

    /**
     * The founder or an administrator changes the guild's name, admission, capacity or metadata; the capacity never
     * goes below the members the guild holds. Opening a guild leaves its waiting requests to be approved or declined.
     */
    public function update(ResourceReference $guild, ResourceReference $by, ?string $name = null, ?Admission $admission = null, ?int $capacity = null, ?Metadata $metadata = null): Guild
    {
        $found = $this->guild($guild);
        $this->administrator($guild, $by);
        $updated = $found->with($name, $admission, $capacity, $metadata);

        if ($updated->capacity < $this->store->countMembers($guild->toString())) {
            throw GuildRefused::because(Refusal::BelowMembers);
        }

        $this->store->saveGuild($updated);
        $this->tell($guild, Change::Updated, null, $by);

        return $updated;
    }

    /**
     * The founder dissolves the guild, its members and its requests with it.
     */
    public function dissolve(ResourceReference $guild, ResourceReference $by): void
    {
        $this->founder($guild, $by);
        $this->store->removeGuild($guild->toString());
        $this->tell($guild, Change::Dissolved, null, $by);
    }

    public function membership(ResourceReference $guild, ResourceReference $member): ?Membership
    {
        return $this->store->membership($guild->toString(), $member->toString());
    }

    public function isMember(ResourceReference $member, ResourceReference $guild): bool
    {
        return $this->membership($guild, $member) instanceof Membership;
    }

    /**
     * The guild's members: the founder first, then the administrators, then the members, each the earliest entered
     * first.
     *
     * @return list<Membership>
     */
    public function members(ResourceReference $guild): array
    {
        $this->guild($guild);
        $members = $this->store->memberships($guild->toString());
        $byRole = [Role::Founder->value => [], Role::Administrator->value => [], Role::Member->value => []];

        foreach ($members as $membership) {
            $byRole[$membership->role->value][] = $membership;
        }

        return [...$byRole[Role::Founder->value], ...$byRole[Role::Administrator->value], ...$byRole[Role::Member->value]];
    }

    /**
     * The references of the guild's members, the earliest entered first: what a team quest or a leaderboard's circle
     * takes.
     *
     * @return list<ResourceReference>
     */
    public function references(ResourceReference $guild): array
    {
        $this->guild($guild);

        return array_map(static fn (Membership $membership): ResourceReference => $membership->member, $this->store->memberships($guild->toString()));
    }

    /**
     * The guilds the member belongs to, the earliest entered first.
     *
     * @return list<Membership>
     */
    public function guildsOf(ResourceReference $member): array
    {
        return $this->store->membershipsOf($member->toString());
    }

    /**
     * Erases what the guilds keep of the member: their requests and their memberships. A guild they founded passes to
     * its earliest administrator, else to its earliest member, and is dissolved when they were its only member.
     */
    public function erase(ResourceReference $member): void
    {
        foreach ($this->store->requestsOf($member->toString()) as $request) {
            $this->store->removeRequest($request->guild->toString(), $member->toString());
            $this->tell($request->guild, Change::Withdrawn, $member, null);
        }

        foreach ($this->store->membershipsOf($member->toString()) as $membership) {
            $guild = $membership->guild;
            $this->store->removeMembership($guild->toString(), $member->toString());

            if (Role::Founder !== $membership->role) {
                $this->tell($guild, Change::Left, $member, null);

                continue;
            }

            $heir = $this->heir($guild);

            if (!$heir instanceof Membership) {
                $this->store->removeGuild($guild->toString());
                $this->tell($guild, Change::Dissolved, null, null);

                continue;
            }

            $this->store->saveMembership($heir->as(Role::Founder));
            $this->tell($guild, Change::Transferred, $heir->member, null);
        }
    }

    private function heir(ResourceReference $guild): ?Membership
    {
        $members = $this->store->memberships($guild->toString());

        foreach ($members as $membership) {
            if (Role::Administrator === $membership->role) {
                return $membership;
            }
        }

        return $members[0] ?? null;
    }

    private function enter(Guild $guild, ResourceReference $member, Change $change, ?ResourceReference $by): Membership
    {
        if ($this->store->countMembers($guild->reference->toString()) >= $guild->capacity) {
            throw GuildRefused::because(Refusal::Full);
        }

        $membership = new Membership($guild->reference, $member, Role::Member, $this->clock->now());
        $this->store->saveMembership($membership);
        $this->tell($guild->reference, $change, $member, $by);

        return $membership;
    }

    private function checkRoomFor(ResourceReference $member): void
    {
        $held = \count($this->store->membershipsOf($member->toString())) + \count($this->store->requestsOf($member->toString()));

        if ($held >= $this->limits->guildsPerMember) {
            throw GuildRefused::because(Refusal::TooManyGuilds);
        }
    }

    private function reassign(ResourceReference $guild, ResourceReference $member, ResourceReference $by, Role $from, Role $to, Change $change): Membership
    {
        $this->founder($guild, $by);
        $membership = $this->member($guild, $member);

        if ($from !== $membership->role) {
            throw GuildRefused::because(Refusal::NotAllowed);
        }

        $reassigned = $membership->as($to);
        $this->store->saveMembership($reassigned);
        $this->tell($guild, $change, $member, $by);

        return $reassigned;
    }

    private function pendingRequest(ResourceReference $guild, ResourceReference $member): void
    {
        if (!$this->store->request($guild->toString(), $member->toString()) instanceof JoinRequest) {
            throw GuildRefused::because(Refusal::NoRequest);
        }
    }

    private function member(ResourceReference $guild, ResourceReference $member): Membership
    {
        $this->guild($guild);

        return $this->membership($guild, $member) ?? throw GuildRefused::because(Refusal::NotMember);
    }

    private function administrator(ResourceReference $guild, ResourceReference $member): Membership
    {
        $membership = $this->member($guild, $member);

        if (!$membership->role->administers()) {
            throw GuildRefused::because(Refusal::NotAllowed);
        }

        return $membership;
    }

    private function founder(ResourceReference $guild, ResourceReference $member): Membership
    {
        $membership = $this->member($guild, $member);

        if (Role::Founder !== $membership->role) {
            throw GuildRefused::because(Refusal::NotAllowed);
        }

        return $membership;
    }

    private function tell(ResourceReference $guild, Change $change, ?ResourceReference $member, ?ResourceReference $by): void
    {
        $event = new GuildChanged($guild->toString(), $change, $member?->toString(), $by?->toString(), $this->clock->now());

        foreach ($this->observers as $observer) {
            $observer->changed($event);
        }
    }
}
