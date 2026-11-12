<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\League;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Exception\PeriodNotOver;
use Tilttek\Leaderboards\Exception\UnknownBoard;
use Tilttek\Leaderboards\Exception\UnknownPeriod;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Store\LeagueStore;
use Tilttek\Leaderboards\Store\ScoreStore;

/**
 * The leagues of the weekly boards that have one: a member enters a division of their tier the first time they score
 * in a week — a new member at the lowest tier —, among the members of their cohort when the divisions are formed by
 * the volume of the week before, and at the end of the week, in the board's timezone, the first of each
 * division go up a tier, the last go down, and the others stay. A member who takes no part in a week is neither moved
 * nor counted.
 *
 * A week is settled once, by `settleDue()` from a scheduler soon after it ends; a member who enters a division before
 * that settles the weeks still open before theirs first, so that they enter at the tier they reached.
 */
final readonly class Leagues
{
    public function __construct(
        private Boards $boards,
        private ScoreStore $scores,
        private LeagueStore $store,
        private ClockInterface $clock,
        private ?LeagueObserver $observer = null,
    ) {
    }

    /**
     * Places the member in a division of the board's week the moment falls in, when they have none yet; null for a
     * board without a league, or a week already settled.
     *
     * @throws UnknownBoard
     */
    public function enter(string $board, ResourceReference $member, DateTimeImmutable $at): ?Membership
    {
        $definition = $this->boards->get($board);
        $league = $definition->league;
        $week = $definition->schedule->at($at);

        if (!$league instanceof League || !$week instanceof Period) {
            return null;
        }

        $held = $this->store->membership($board, $week->code, $member);

        if ($held instanceof Membership) {
            return $held;
        }

        $this->settleBefore($definition, min($at, $this->clock->now()));

        if ($this->store->isSettled($board, $week->code)) {
            return null;
        }

        $tier = $league->tiers[$league->level($this->store->tier($board, $member) ?? $league->lowest())];
        $lastWeek = $definition->schedule->previous($at);
        $cohort = Formation::Volume === $league->formedBy && $lastWeek instanceof Period
            ? $league->cohortOf($this->scores->score($board, $lastWeek->code, $member)->value ?? 0)
            : League::NO_COHORT;

        return $this->store->join($board, $week->code, $member, $tier, $at, $league->divisionSize, $cohort);
    }

    /**
     * The member's membership in the board's week — the one under way by default —; null when they take no part in
     * it.
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     * @throws InvalidLeaderboard when the board has no league
     */
    public function membership(string $board, ResourceReference $member, ?string $week = null): ?Membership
    {
        return $this->store->membership($board, $this->week($board, $week)->code, $member);
    }

    /**
     * The members of the membership's division, as a circle to rank them among themselves.
     */
    public function circle(Membership $membership): Circle
    {
        return new Circle(...array_map(
            static fn (Membership $other): ResourceReference => $other->member,
            $this->store->division($membership->board, $membership->week, $membership->tier, $membership->division),
        ));
    }

    /**
     * The members of the membership's division.
     *
     * @return list<Membership>
     */
    public function divisionOf(Membership $membership): array
    {
        return $this->store->division($membership->board, $membership->week, $membership->tier, $membership->division);
    }

    /**
     * The member's tier on the board: the one they reached, or the lowest for a member who never took part.
     *
     * @throws UnknownBoard
     * @throws InvalidLeaderboard when the board has no league
     */
    public function tier(string $board, ResourceReference $member): string
    {
        $league = $this->leagueOf($this->boards->get($board));

        return $league->tiers[$league->level($this->store->tier($board, $member) ?? $league->lowest())];
    }

    /**
     * Settles the board's week: in each division, ranked by score — a member left without a score last, in their order
     * of arrival —, the first go up and the last go down. The observer is told once; null when the week was already
     * settled.
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     * @throws InvalidLeaderboard when the board has no league
     * @throws PeriodNotOver
     */
    public function settle(string $board, string $week): ?Settlement
    {
        $period = $this->week($board, $week);

        if (!$period->isOverAt($this->clock->now())) {
            throw PeriodNotOver::of($board, $week);
        }

        return $this->settlePeriod($this->boards->get($board), $period->code);
    }

    /**
     * Settles every week over that a board with a league has not settled, the earliest first, and returns the
     * settlements.
     *
     * @return list<Settlement>
     */
    public function settleDue(): array
    {
        $settled = [];

        foreach ($this->boards->all() as $board) {
            if ($board->league instanceof League) {
                array_push($settled, ...$this->settleBefore($board, $this->clock->now()));
            }
        }

        return $settled;
    }

    /**
     * What the leagues hold about the member: their tier on each board and their memberships.
     *
     * @return array{tiers: array<string, string>, memberships: list<array{board: string, week: string, tier: string, division: int, cohort: int, joined_at: string, movement: ?string}>}
     */
    public function export(ResourceReference $member): array
    {
        return [
            'tiers' => $this->store->tiersOf($member),
            'memberships' => array_map(static fn (Membership $membership): array => $membership->toArray(), $this->store->membershipsOf($member)),
        ];
    }

    /**
     * Erases the member from the leagues: their tiers and memberships.
     *
     * @return int how many rows were removed
     */
    public function erase(ResourceReference $member): int
    {
        return $this->store->eraseMember($member);
    }

    /**
     * Settles the weeks of the board still open that were over at that moment, the earliest first.
     *
     * @return list<Settlement>
     */
    private function settleBefore(Board $board, DateTimeImmutable $until): array
    {
        $settled = [];

        foreach ($this->store->unsettled($board->name) as $code) {
            $period = $board->schedule->named($code);

            if (!$period instanceof Period || !$period->isOverAt($until)) {
                continue;
            }

            $settlement = $this->settlePeriod($board, $code);

            if ($settlement instanceof Settlement) {
                $settled[] = $settlement;
            }
        }

        return $settled;
    }

    private function settlePeriod(Board $board, string $week): ?Settlement
    {
        $league = $this->leagueOf($board);
        $byTier = [];

        foreach ($this->store->memberships($board->name, $week) as $membership) {
            $byTier[$membership->tier][$membership->division][] = $membership;
        }

        $memberships = [];
        $tiers = [];

        foreach ($byTier as $divisions) {
            foreach ($divisions as $division) {
                $ordered = $this->ordered($board->name, $week, $division);
                $count = \count($ordered);
                $promoted = $league->promotedFrom($division[0]->tier, $count);
                $relegated = $league->relegatedFrom($division[0]->tier, $count);

                foreach ($ordered as $position => $membership) {
                    $movement = match (true) {
                        $position < $promoted => Movement::Promoted,
                        $position >= $count - $relegated => Movement::Relegated,
                        default => Movement::Stayed,
                    };
                    $memberships[] = $membership->settled($movement);
                    $tiers[$membership->member->toString()] = $league->after($membership->tier, $movement);
                }
            }
        }

        $settlement = new Settlement($board->name, $week, $this->clock->now(), $memberships);

        if (!$this->store->settle($settlement, $tiers)) {
            return null;
        }

        $this->observer?->leagueSettled($settlement);

        return $settlement;
    }

    /**
     * The division in rank order: by score, as the board orders it, then the members without a score in their order
     * of arrival.
     *
     * @param non-empty-list<Membership> $division
     *
     * @return list<Membership>
     */
    private function ordered(string $board, string $week, array $division): array
    {
        $byMember = [];

        foreach ($division as $membership) {
            $byMember[$membership->member->toString()] = $membership;
        }

        $ordered = [];

        foreach ($this->scores->top($board, $week, \count($division), $this->boards->get($board)->ranking, new Circle(...array_map(static fn (Membership $membership): ResourceReference => $membership->member, $division))) as $score) {
            $ordered[] = $byMember[$score->member->toString()];
            unset($byMember[$score->member->toString()]);
        }

        return [...$ordered, ...array_values($byMember)];
    }

    /**
     * @throws UnknownBoard
     * @throws UnknownPeriod
     * @throws InvalidLeaderboard
     */
    private function week(string $board, ?string $code): Period
    {
        $definition = $this->boards->get($board);
        $this->leagueOf($definition);

        $period = null === $code ? $definition->schedule->at($this->clock->now()) : $definition->schedule->named($code);

        return $period ?? throw UnknownPeriod::named($board, (string) $code);
    }

    /**
     * The league of the board.
     *
     * @throws UnknownBoard
     * @throws InvalidLeaderboard when the board has no league
     */
    public function league(string $board): League
    {
        return $this->leagueOf($this->boards->get($board));
    }

    private function leagueOf(Board $board): League
    {
        return $board->league ?? throw InvalidLeaderboard::because(\sprintf('The board "%s" has no league.', $board->name));
    }
}
