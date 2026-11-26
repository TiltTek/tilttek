<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Store;

use DateTimeImmutable;
use Tilttek\Guilds\Contract\GuildStore;
use Tilttek\Guilds\Guild;
use Tilttek\Guilds\JoinRequest;
use Tilttek\Guilds\Membership;

/**
 * The guilds kept in memory, for tests and for an application that rebuilds them at each start.
 */
final class InMemoryGuildStore implements GuildStore
{
    /**
     * @var array<string, Guild>
     */
    private array $guilds = [];

    /**
     * @var array<string, array<string, Membership>> by guild, then member
     */
    private array $memberships = [];

    /**
     * @var array<string, array<string, JoinRequest>> by guild, then member
     */
    private array $requests = [];

    public function guild(string $guild): ?Guild
    {
        return $this->guilds[$guild] ?? null;
    }

    public function saveGuild(Guild $guild): void
    {
        $this->guilds[$guild->reference->toString()] = $guild;
    }

    public function removeGuild(string $guild): void
    {
        unset($this->guilds[$guild], $this->memberships[$guild], $this->requests[$guild]);
    }

    public function membership(string $guild, string $member): ?Membership
    {
        return $this->memberships[$guild][$member] ?? null;
    }

    public function memberships(string $guild): array
    {
        return $this->sortMemberships($this->memberships[$guild] ?? []);
    }

    public function countMembers(string $guild): int
    {
        return \count($this->memberships[$guild] ?? []);
    }

    public function saveMembership(Membership $membership): void
    {
        $this->memberships[$membership->guild->toString()][$membership->member->toString()] = $membership;
    }

    public function removeMembership(string $guild, string $member): void
    {
        unset($this->memberships[$guild][$member]);
    }

    public function membershipsOf(string $member): array
    {
        $found = [];

        foreach ($this->memberships as $members) {
            if (isset($members[$member])) {
                $found[] = $members[$member];
            }
        }

        return $this->sortMemberships($found);
    }

    public function request(string $guild, string $member): ?JoinRequest
    {
        return $this->requests[$guild][$member] ?? null;
    }

    public function requests(string $guild): array
    {
        return $this->sortRequests($this->requests[$guild] ?? []);
    }

    public function countRequests(string $guild): int
    {
        return \count($this->requests[$guild] ?? []);
    }

    public function saveRequest(JoinRequest $request): void
    {
        $this->requests[$request->guild->toString()][$request->member->toString()] = $request;
    }

    public function removeRequest(string $guild, string $member): void
    {
        unset($this->requests[$guild][$member]);
    }

    public function requestsOf(string $member): array
    {
        $found = [];

        foreach ($this->requests as $requests) {
            if (isset($requests[$member])) {
                $found[] = $requests[$member];
            }
        }

        return $this->sortRequests($found);
    }

    /**
     * @param array<Membership> $memberships
     *
     * @return list<Membership>
     */
    private function sortMemberships(array $memberships): array
    {
        usort($memberships, static fn (Membership $a, Membership $b): int => self::order($a->since, $b->since, $a->guild->toString().' '.$a->member->toString(), $b->guild->toString().' '.$b->member->toString()));

        return $memberships;
    }

    /**
     * @param array<JoinRequest> $requests
     *
     * @return list<JoinRequest>
     */
    private function sortRequests(array $requests): array
    {
        usort($requests, static fn (JoinRequest $a, JoinRequest $b): int => self::order($a->requestedAt, $b->requestedAt, $a->guild->toString().' '.$a->member->toString(), $b->guild->toString().' '.$b->member->toString()));

        return $requests;
    }

    /**
     * The earlier moment first, then the tie-breaks in byte order.
     */
    private static function order(DateTimeImmutable $a, DateTimeImmutable $b, string $tieA, string $tieB): int
    {
        $byTime = $a <=> $b;

        return 0 !== $byTime ? $byTime : strcmp($tieA, $tieB);
    }
}
