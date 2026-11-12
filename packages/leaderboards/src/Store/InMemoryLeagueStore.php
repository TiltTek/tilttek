<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Settlement;

/**
 * The leagues in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryLeagueStore implements LeagueStore
{
    /**
     * @var array<string, string> by board and member
     */
    private array $tiers = [];

    /**
     * @var array<string, Membership> by board, week and member
     */
    private array $memberships = [];

    /**
     * @var array<string, DateTimeImmutable> when each week was settled, by board and week
     */
    private array $settled = [];

    #[Override]
    public function tier(string $board, ResourceReference $member): ?string
    {
        return $this->tiers[self::key($board, $member->toString())] ?? null;
    }

    #[Override]
    public function membership(string $board, string $week, ResourceReference $member): ?Membership
    {
        return $this->memberships[self::key($board, $week, $member->toString())] ?? null;
    }

    #[Override]
    public function join(string $board, string $week, ResourceReference $member, string $tier, DateTimeImmutable $at, int $size, int $cohort = League::NO_COHORT): Membership
    {
        $held = $this->membership($board, $week, $member);

        if ($held instanceof Membership) {
            return $held;
        }

        $divisions = [];

        foreach ($this->memberships as $membership) {
            if ($membership->board === $board && $membership->week === $week && $membership->tier === $tier) {
                $divisions[$membership->division] ??= ['cohort' => $membership->cohort, 'members' => 0];
                ++$divisions[$membership->division]['members'];
            }
        }

        $division = array_find_key($divisions, static fn (array $held): bool => $held['cohort'] === $cohort && $held['members'] < $size) ?? 1 + max([0, ...array_keys($divisions)]);

        return $this->memberships[self::key($board, $week, $member->toString())] = new Membership($board, $week, $member, $tier, $division, $at, cohort: $cohort);
    }

    #[Override]
    public function division(string $board, string $week, string $tier, int $division): array
    {
        return array_values(array_filter($this->memberships($board, $week), static fn (Membership $membership): bool => $membership->tier === $tier && $membership->division === $division));
    }

    #[Override]
    public function memberships(string $board, string $week): array
    {
        return $this->sorted(array_filter($this->memberships, static fn (Membership $membership): bool => $membership->board === $board && $membership->week === $week));
    }

    #[Override]
    public function isSettled(string $board, string $week): bool
    {
        return isset($this->settled[self::key($board, $week)]);
    }

    #[Override]
    public function unsettled(string $board): array
    {
        $weeks = [];

        foreach ($this->memberships as $membership) {
            if ($membership->board === $board && !$this->isSettled($board, $membership->week)) {
                $weeks[$membership->week] = $membership->week;
            }
        }

        sort($weeks, \SORT_STRING);

        return $weeks;
    }

    #[Override]
    public function settle(Settlement $settlement, array $tiers): bool
    {
        if ($this->isSettled($settlement->board, $settlement->week)) {
            return false;
        }

        foreach ($settlement->memberships as $membership) {
            $this->memberships[self::key($membership->board, $membership->week, $membership->member->toString())] = $membership;
        }

        foreach ($tiers as $member => $tier) {
            $this->tiers[self::key($settlement->board, $member)] = $tier;
        }

        $this->settled[self::key($settlement->board, $settlement->week)] = $settlement->settledAt;

        return true;
    }

    #[Override]
    public function tiersOf(ResourceReference $member): array
    {
        $tiers = [];

        foreach ($this->tiers as $key => $tier) {
            /** @var array{string, string} $parts */
            $parts = json_decode($key, true, flags: \JSON_THROW_ON_ERROR);

            if ($parts[1] === $member->toString()) {
                $tiers[$parts[0]] = $tier;
            }
        }

        ksort($tiers, \SORT_STRING);

        return $tiers;
    }

    #[Override]
    public function membershipsOf(ResourceReference $member): array
    {
        $memberships = array_filter($this->memberships, static fn (Membership $membership): bool => $membership->member->equals($member));
        usort($memberships, static fn (Membership $a, Membership $b): int => [$a->board, $a->week] <=> [$b->board, $b->week]);

        return $memberships;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->tiers) + \count($this->memberships);
        $this->tiers = array_diff_key($this->tiers, array_flip(array_map(static fn (string $board): string => self::key($board, $member->toString()), array_keys($this->tiersOf($member)))));
        $this->memberships = array_filter($this->memberships, static fn (Membership $membership): bool => !$membership->member->equals($member));

        return $count - \count($this->tiers) - \count($this->memberships);
    }

    private static function key(string ...$parts): string
    {
        return json_encode($parts, \JSON_THROW_ON_ERROR);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->tiers = [];
        $this->memberships = [];
        $this->settled = [];
    }

    /**
     * @param array<string, Membership> $memberships
     *
     * @return list<Membership>
     */
    private function sorted(array $memberships): array
    {
        usort($memberships, static fn (Membership $a, Membership $b): int => [$a->tier, $a->division, $a->joinedAt, $a->member->toString()] <=> [$b->tier, $b->division, $b->joinedAt, $b->member->toString()]);

        return $memberships;
    }
}
