<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Contract;

use Tilttek\Guilds\Guild;
use Tilttek\Guilds\JoinRequest;
use Tilttek\Guilds\Membership;

/**
 * Where the guilds, their members and their waiting requests are kept. Guilds, members and requests are named by
 * their references written `kind:identifier`.
 *
 * The library checks a guild's capacity before it writes a membership: an application that serves concurrent
 * requests runs each act of `Guilds` in a transaction, or under a lock on the guild, so that two members never take
 * the last place together. An implementation keeps no state between two requests beside its storage.
 */
interface GuildStore
{
    public function guild(string $guild): ?Guild;

    public function saveGuild(Guild $guild): void;

    /**
     * Removes the guild with its members and its requests.
     */
    public function removeGuild(string $guild): void;

    public function membership(string $guild, string $member): ?Membership;

    /**
     * The guild's members, the earliest entered first, ties in the order of their references.
     *
     * @return list<Membership>
     */
    public function memberships(string $guild): array;

    public function countMembers(string $guild): int;

    /**
     * Writes the membership, replacing the member's earlier one in that guild.
     */
    public function saveMembership(Membership $membership): void;

    public function removeMembership(string $guild, string $member): void;

    /**
     * The memberships of the member, in every guild, the earliest entered first.
     *
     * @return list<Membership>
     */
    public function membershipsOf(string $member): array;

    public function request(string $guild, string $member): ?JoinRequest;

    /**
     * The guild's waiting requests, the oldest first, ties in the order of their members.
     *
     * @return list<JoinRequest>
     */
    public function requests(string $guild): array;

    public function countRequests(string $guild): int;

    public function saveRequest(JoinRequest $request): void;

    public function removeRequest(string $guild, string $member): void;

    /**
     * The member's waiting requests, in every guild, the oldest first.
     *
     * @return list<JoinRequest>
     */
    public function requestsOf(string $member): array;
}
