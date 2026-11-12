<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Exception\PeriodNotOver;
use Tilttek\Leaderboards\Exception\UnknownBoard;
use Tilttek\Leaderboards\Exception\UnknownPeriod;
use Tilttek\Leaderboards\League\Division;
use Tilttek\Leaderboards\League\Leagues;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Store\AppearanceStore;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\Leaderboards\Store\SnapshotStore;

/**
 * The leaderboards of an application: a board in a period as one viewer sees it, a member's rank, the values the
 * application reports for the boards it measures itself, each member's choice of how they appear, the snapshots taken
 * at the end of a period, a member's division on a weekly board with a league, and a member's export and erasure.
 *
 * Nobody is ranked publicly without choosing it: a member who has not chosen to appear holds their rank, which they
 * alone see as theirs; the others see a line without a name.
 */
final readonly class Leaderboards
{
    public const int MAX_LIMIT = 100;

    /**
     * How many lines a standing or a snapshot shows when nobody says.
     */
    public const int DEFAULT_LIMIT = 10;

    public function __construct(
        private Boards $boards,
        private ScoreStore $scores,
        private SnapshotStore $snapshots,
        private AppearanceStore $appearances,
        private ClockInterface $clock,
        private ?LeaderboardObserver $observer = null,
        private ?Leagues $leagues = null,
        #[SensitiveParameter]
        private string $secret = '',
    ) {
    }

    public function boards(): Boards
    {
        return $this->boards;
    }

    /**
     * The board's period the code names, or the one under way when no code is given.
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     */
    public function period(string $board, ?string $code = null): Period
    {
        $schedule = $this->boards->get($board)->schedule;

        if (null === $code) {
            return $schedule->at($this->clock->now()) ?? throw UnknownPeriod::now($board);
        }

        return $schedule->named($code) ?? throw UnknownPeriod::named($board, $code);
    }

    /**
     * The board in the period — the one under way by default — as the viewer sees it: its first `limit` lines (1 to
     * 100), the viewer's own line, and how many members are ranked; among the circle's members only, when one is
     * given, ranked among themselves; for the object given, on a board ranked by object.
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     */
    public function standing(string $board, ?ResourceReference $viewer = null, ?string $period = null, int $limit = self::DEFAULT_LIMIT, ?Circle $among = null, ?ResourceReference $object = null): Standing
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidLeaderboard::because(\sprintf('A standing shows 1 to %d lines; %d is not one.', self::MAX_LIMIT, $limit));
        }

        $definition = $this->boards->get($board);
        $key = $definition->key($object);
        $code = $this->period($board, $period);
        $lines = array_map(
            static fn (array $ranked): array => ['rank' => $ranked['rank'], 'member' => $ranked['score']->member, 'value' => $ranked['score']->value],
            $definition->ranking->rank($this->scores->top($key, $code->code, $limit, $definition->ranking, $among)),
        );

        return new Standing(
            $definition,
            $code,
            $this->present($lines, $viewer),
            $viewer instanceof ResourceReference ? $this->rank($board, $viewer, $code->code, $among, $object) : null,
            $this->scores->participants($key, $code->code, $among),
        );
    }

    /**
     * The member's own line on the board in the period — the one under way by default —, among the circle's members
     * when one is given, for the object given on a board ranked by object; null when they hold no score there, or are
     * not in the circle.
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     */
    public function rank(string $board, ResourceReference $member, ?string $period = null, ?Circle $among = null, ?ResourceReference $object = null): ?Row
    {
        $definition = $this->boards->get($board);
        $score = $this->scores->score($definition->key($object), $this->period($board, $period)->code, $member);

        if (!$score instanceof Score || ($among instanceof Circle && !$among->has($member))) {
            return null;
        }

        return $this->present([['rank' => 1 + $this->scores->ahead($score, $definition->ranking, $among), 'member' => $member, 'value' => $score->value]], $member)[0];
    }

    /**
     * Reports a member's value on a board whose measure the application keeps — a level, a balance, the longest
     * streak —, with the values of its tie-breaks, at the moment it holds, now by default. A board that keeps the
     * latest value ignores a report older than the one it holds, or the same; one that keeps the highest ignores a
     * value that ranks no better, its tie-breaks included. False when nothing changed, or when the moment falls in no
     * period. On a board with a league, a member whose value is kept enters a division.
     *
     * @param list<int> $tieBreaks one value for each tie-break of the board
     *
     * @throws UnknownBoard
     * @throws InvalidLeaderboard when the board reads its measure from the journal, or the values are not a score
     */
    public function report(string $board, ResourceReference $member, int $value, ?DateTimeImmutable $at = null, array $tieBreaks = []): bool
    {
        $definition = $this->boards->get($board);

        if ($definition->measure->readsJournal()) {
            throw InvalidLeaderboard::because(\sprintf('The board "%s" reads its measure from the journal: nothing is reported to it.', $board));
        }

        if (\count($tieBreaks) !== \count($definition->tieBreaks)) {
            throw InvalidLeaderboard::because(\sprintf('The board "%s" orders a tie by %d values; %d were reported.', $board, \count($definition->tieBreaks), \count($tieBreaks)));
        }

        $at ??= $this->clock->now();
        $period = $definition->schedule->at($at);

        if (!$period instanceof Period) {
            return false;
        }

        $score = new Score($board, $period->code, $member, $value, $at, $tieBreaks);
        $held = $this->scores->score($board, $period->code, $member);

        if ($held instanceof Score && (Measure::Latest === $definition->measure ? $held->reachedAt > $at || 0 === $definition->ranking->compareValues($held, $score) : $definition->ranking->compareValues($score, $held) >= 0)) {
            return false;
        }

        $this->scores->save($score);
        $this->leagues?->enter($board, $member, $at);

        return true;
    }

    /**
     * The viewer's division on a weekly board with a league, in the week — the one under way by default —, ranked among
     * its members as the viewer sees them; null when the viewer takes no part in that week.
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     * @throws InvalidLeaderboard when the board has no league, or the leagues are not given
     */
    public function division(string $board, ResourceReference $viewer, ?string $period = null): ?Division
    {
        $leagues = $this->leagues ?? throw InvalidLeaderboard::because('The leaderboards are not given their leagues.');
        $membership = $leagues->membership($board, $viewer, $period);

        if (!$membership instanceof Membership) {
            return null;
        }

        $league = $leagues->league($board);
        $members = \count($leagues->divisionOf($membership));

        return new Division(
            $membership,
            $this->standing($board, $viewer, $membership->week, min($members, self::MAX_LIMIT), $leagues->circle($membership)),
            $members,
            $league->promotedFrom($membership->tier, $members),
            $league->relegatedFrom($membership->tier, $members),
        );
    }

    /**
     * The titles the member holds now: those of the boards whose score in the period under way reaches the threshold,
     * each once, sorted. A board ranked by object gives no title.
     *
     * @return list<string>
     */
    public function titles(ResourceReference $member): array
    {
        $titles = [];

        foreach ($this->boards->all() as $board) {
            if ($board->title instanceof Title && !$board->perObject && $this->holds($board->name, $member)) {
                $titles[$board->title->name] = $board->title->name;
            }
        }

        sort($titles, \SORT_STRING);

        return $titles;
    }

    /**
     * Whether the member holds the board's title now — for the object given, on a board ranked by object —: their
     * score in the period under way reaches its threshold and, for a title of the leader, nobody ranks before them.
     * False on a board without a title, or between two seasons.
     *
     * @throws UnknownBoard
     */
    public function holds(string $board, ResourceReference $member, ?ResourceReference $object = null): bool
    {
        $definition = $this->boards->get($board);
        $title = $definition->title;
        $period = $definition->schedule->at($this->clock->now());

        if (!$title instanceof Title || !$period instanceof Period) {
            return false;
        }

        $score = $this->scores->score($definition->key($object), $period->code, $member);

        return $score instanceof Score && $title->isHeldWith($score) && (!$title->leader || 0 === $this->scores->ahead($score, $definition->ranking));
    }

    /**
     * The teams ranked on the board in the period — the one under way by default —, for the object given on a board
     * ranked by object: each one's members' scores added up, with the roll of luck the board enables; the highest
     * total first, equal totals sharing a rank and listed by name. 1 to 100 teams, each named once.
     *
     * @param list<Team> $teams
     *
     * @return list<TeamRow>
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     * @throws InvalidLeaderboard when the teams are too many or named twice, or the board rolls luck without a secret
     */
    public function teams(string $board, array $teams, ?string $period = null, ?ResourceReference $object = null): array
    {
        $names = array_map(static fn (Team $team): string => $team->name, $teams);

        if ([] === $teams || \count($teams) > self::MAX_LIMIT || \count(array_unique($names)) !== \count($names)) {
            throw InvalidLeaderboard::because(\sprintf('A ranking of teams holds 1 to %d teams, each named once.', self::MAX_LIMIT));
        }

        $definition = $this->boards->get($board);
        $key = $definition->key($object);
        $code = $this->period($board, $period)->code;
        $rows = [];

        foreach ($teams as $team) {
            $scores = $this->scores->top($key, $code, \count($team->members->members), $definition->ranking, $team->members);
            $score = min(Score::MAX, array_sum(array_map(static fn (Score $held): int => $held->value, $scores)));
            $luck = $definition->chance?->roll($this->secret, $key, $code, $team->name) ?? 0;
            $rows[] = ['team' => $team->name, 'scoring' => \count($scores), 'score' => $score, 'luck' => $luck, 'total' => $score + $luck];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['total'], $a['team']] <=> [$a['total'], $b['team']]);
        $ranked = [];

        foreach ($rows as $index => $row) {
            $before = $ranked[$index - 1] ?? null;
            $ranked[] = new TeamRow($before instanceof TeamRow && $before->total === $row['total'] ? $before->rank : $index + 1, $row['team'], $row['scoring'], $row['score'], $row['luck'], $row['total']);
        }

        return $ranked;
    }

    /**
     * Keeps the member's choice of how the others see them on every board.
     */
    public function appear(ResourceReference $member, Appearance $appearance): void
    {
        $this->appearances->choose($member, $appearance);
    }

    public function appearance(ResourceReference $member): Appearance
    {
        return $this->appearances->of([$member])[$member->toString()] ?? Appearance::hidden();
    }

    /**
     * Freezes the board at the end of the period — for the object given, on a board ranked by object —: its first
     * members, as many as the board keeps. The observer is told once; asked again, the snapshot taken is returned.
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     * @throws PeriodNotOver
     */
    public function snapshot(string $board, string $period, ?ResourceReference $object = null): Snapshot
    {
        $definition = $this->boards->get($board);
        $key = $definition->key($object);
        $code = $this->period($board, $period);
        $now = $this->clock->now();

        if (!$code->isOverAt($now)) {
            throw PeriodNotOver::of($board, $period);
        }

        $taken = $this->snapshots->find($key, $period);

        if ($taken instanceof Snapshot) {
            return $taken;
        }

        $placings = array_map(
            static fn (array $ranked): Placing => new Placing($key, $period, $ranked['score']->member, $ranked['rank'], $ranked['score']->value, $ranked['score']->reachedAt),
            $definition->ranking->rank($this->scores->top($key, $period, $definition->snapshotSize, $definition->ranking)),
        );

        $snapshot = new Snapshot($key, $period, $now, $this->scores->participants($key, $period), $placings);

        if ($this->snapshots->save($snapshot)) {
            $this->observer?->snapshotTaken($snapshot);
        }

        return $snapshot;
    }

    /**
     * Takes the snapshot of the period each board ended last, when it has none yet, and returns those taken. A board
     * ranked by object is left to the application, which knows its objects and takes each one's snapshot.
     *
     * @return list<Snapshot>
     */
    public function snapshotDue(): array
    {
        $taken = [];

        foreach ($this->boards->all() as $board) {
            $period = $board->perObject ? null : $board->schedule->previous($this->clock->now());

            if ($period instanceof Period && !$this->snapshots->find($board->name, $period->code) instanceof Snapshot) {
                $taken[] = $this->snapshot($board->name, $period->code);
            }
        }

        return $taken;
    }

    /**
     * The snapshot of the board for the period as the viewer sees it, by each member's choice now: its first `limit`
     * lines (1 to 1000) and the viewer's own — for the object given, on a board ranked by object —; null when none was
     * taken.
     *
     * @throws UnknownBoard
     * @throws UnknownPeriod
     */
    public function frozen(string $board, string $period, ?ResourceReference $viewer = null, int $limit = self::DEFAULT_LIMIT, ?ResourceReference $object = null): ?Standing
    {
        if ($limit < 1 || $limit > Board::MAX_SNAPSHOT) {
            throw InvalidLeaderboard::because(\sprintf('A snapshot shows 1 to %d lines; %d is not one.', Board::MAX_SNAPSHOT, $limit));
        }

        $definition = $this->boards->get($board);
        $code = $this->period($board, $period);
        $snapshot = $this->snapshots->find($definition->key($object), $period);

        if (!$snapshot instanceof Snapshot) {
            return null;
        }

        $lines = array_map(static fn (Placing $placing): array => ['rank' => $placing->rank, 'member' => $placing->member, 'value' => $placing->value], $snapshot->placings);
        $own = $viewer instanceof ResourceReference ? array_find($lines, static fn (array $line): bool => $line['member']->equals($viewer)) : null;

        return new Standing($definition, $code, $this->present(\array_slice($lines, 0, $limit), $viewer), null === $own ? null : $this->present([$own], $viewer)[0], $snapshot->participants, $snapshot->takenAt);
    }

    /**
     * What the boards hold about the member: their choice of appearance, their scores, their lines in the snapshots,
     * and their tiers and memberships in the leagues.
     *
     * @return array{appearance: array{visibility: string, pseudonym: ?string}, scores: list<array{board: string, period: string, value: int, tie_breaks: list<int>, reached_at: string}>, placings: list<array{board: string, period: string, rank: int, value: int, reached_at: string}>, leagues: array{tiers: array<string, string>, memberships: list<array{board: string, week: string, tier: string, division: int, cohort: int, joined_at: string, movement: ?string}>}}
     */
    public function export(ResourceReference $member): array
    {
        $appearance = $this->appearance($member);

        return [
            'appearance' => ['visibility' => $appearance->visibility->value, 'pseudonym' => $appearance->pseudonym],
            'scores' => array_map(static fn (Score $score): array => $score->toArray(), $this->scores->of($member)),
            'placings' => array_map(static fn (Placing $placing): array => $placing->toArray(), $this->snapshots->placings($member)),
            'leagues' => $this->leagues?->export($member) ?? ['tiers' => [], 'memberships' => []],
        ];
    }

    /**
     * Erases the member from the boards: their contributions and scores, their lines in the snapshots, their choice,
     * their tiers and memberships in the leagues.
     */
    public function erase(ResourceReference $member): Erasure
    {
        return new Erasure($this->scores->eraseMember($member), $this->snapshots->eraseMember($member), $this->appearances->eraseMember($member), $this->leagues?->erase($member) ?? 0);
    }

    /**
     * The lines as the viewer sees them, by each member's choice.
     *
     * @param list<array{rank: int, member: ResourceReference, value: int}> $lines
     *
     * @return list<Row>
     */
    private function present(array $lines, ?ResourceReference $viewer): array
    {
        $appearances = $this->appearances->of(array_map(static fn (array $line): ResourceReference => $line['member'], $lines));
        $rows = [];

        foreach ($lines as $line) {
            $appearance = $appearances[$line['member']->toString()] ?? Appearance::hidden();
            $self = $viewer instanceof ResourceReference && $viewer->equals($line['member']);
            $rows[] = new Row($line['rank'], $line['value'], $self || Visibility::Named === $appearance->visibility ? $line['member'] : null, $appearance->pseudonym, $self);
        }

        return $rows;
    }
}
