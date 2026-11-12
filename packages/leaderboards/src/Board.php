<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Context;
use Tilttek\Activity\Verbs;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;

/**
 * A leaderboard: its name, the measure it ranks members on, the schedule of its periods, the verbs and the context
 * conditions of the activities it reads — none for a reported measure —, how it orders a tie — up to two tie-breaks,
 * then the first to reach the score or a shared rank —, how many members a snapshot keeps, and the options that make it
 * more: the league that splits a weekly board into divisions, the days of a sliding window, a ranking for each object
 * of the activities, a title held from a score, and the luck in the score of a team.
 *
 * *The games played this month, in Toronto*: `new Board('games-month', Measure::Count, new
 * Schedule(PeriodKind::Month, new DateTimeZone('America/Toronto')), ['game.played'])`.
 */
final readonly class Board
{
    public const int MAX_SNAPSHOT = 1000;

    public const int MAX_WINDOW = 366;

    /**
     * What separates the name of a board ranked by object from the digest of the object, in the key its scores are
     * kept under.
     */
    public const string OBJECT_SEPARATOR = '@';

    public Verbs $verbs;

    public Context $conditions;

    public Ranking $ranking;

    /**
     * @param list<string>                   $verbs     patterns such as `game.played` or `game.*`, at least one for a measure read from the journal
     * @param array<string, bool|int|string> $context
     * @param list<TieBreak>                 $tieBreaks 0 to 2, read from the journal on a board that reads it, reported on another
     * @param int                            $window    the days of a sliding window, today included — 1 to 366 —, on a board ranked over one; 0 otherwise
     * @param bool                           $perObject whether the board ranks members for each object of the activities — a game, a book, a level —
     */
    public function __construct(
        public string $name,
        public Measure $measure,
        public Schedule $schedule,
        array $verbs = [],
        array $context = [],
        public Ties $ties = Ties::FirstReached,
        public int $snapshotSize = 100,
        public ?League $league = null,
        public array $tieBreaks = [],
        public int $window = 0,
        public bool $perObject = false,
        public ?Title $title = null,
        public ?Chance $chance = null,
    ) {
        Names::check($name, 'board');

        if ($measure->readsJournal() === ([] === $verbs)) {
            throw InvalidLeaderboard::because(\sprintf('The board "%s" reads verbs when its measure comes from the journal, and only then.', $name));
        }

        if (!$measure->readsJournal() && [] !== $context) {
            throw InvalidLeaderboard::because(\sprintf('The board "%s" has a reported measure: it reads no context.', $name));
        }

        if ($snapshotSize < 1 || $snapshotSize > self::MAX_SNAPSHOT) {
            throw InvalidLeaderboard::because(\sprintf('A snapshot keeps 1 to %d members; the board "%s" asks for %d.', self::MAX_SNAPSHOT, $name, $snapshotSize));
        }

        if ($league instanceof League && (PeriodKind::Week !== $schedule->kind || $perObject)) {
            throw InvalidLeaderboard::because(\sprintf('The board "%s" has a league: it ranks by week, and not by object.', $name));
        }

        if ((PeriodKind::Window === $schedule->kind) !== ($window > 0) || $window < 0 || $window > self::MAX_WINDOW) {
            throw InvalidLeaderboard::because(\sprintf('A board ranked over a sliding window counts 1 to %d days, and only such a board counts any; the board "%s" counts %d.', self::MAX_WINDOW, $name, $window));
        }

        if (array_any($tieBreaks, static fn (TieBreak $tieBreak): bool => (Aggregate::Reported === $tieBreak->aggregate) === $measure->readsJournal())) {
            throw InvalidLeaderboard::because(\sprintf('The tie-breaks of the board "%s" are reported when its measure is, and only then.', $name));
        }

        if ($perObject && !$measure->readsJournal()) {
            throw InvalidLeaderboard::because(\sprintf('The board "%s" ranks by object: it reads the objects of the activities in the journal.', $name));
        }

        $this->ranking = new Ranking(array_map(static fn (TieBreak $tieBreak): Direction => $tieBreak->direction, $tieBreaks), $ties);
        $this->verbs = Verbs::of($verbs);
        $this->conditions = new Context($context);
    }

    /**
     * Whether the activity counts on this board: its measure reads the journal, its verb is read, its context holds
     * every condition and the integers the tie-breaks read, and it has an object when the board ranks by object.
     */
    public function matches(Activity $activity): bool
    {
        return $this->measure->readsJournal()
            && $this->verbs->allows($activity->verb)
            && (!$this->perObject || $activity->object instanceof ResourceReference)
            && array_all($this->tieBreaks, static fn (TieBreak $tieBreak): bool => $tieBreak->reads($activity))
            && array_all($this->conditions->values, static fn (bool|int|string $value, string $name): bool => $activity->context->get($name) === $value);
    }

    /**
     * The key the board's scores are kept under: its name, or — on a board ranked by object — its name and a digest of
     * the object, so that the store never holds the object's reference.
     *
     * @throws InvalidLeaderboard when an object is given to a board that does not rank by object, or none to one that does
     */
    public function key(?ResourceReference $object = null): string
    {
        if ($this->perObject !== $object instanceof ResourceReference) {
            throw InvalidLeaderboard::because(\sprintf($this->perObject ? 'The board "%s" ranks by object: say which one.' : 'The board "%s" does not rank by object.', $this->name));
        }

        return $object instanceof ResourceReference ? $this->name.self::OBJECT_SEPARATOR.substr(hash('sha256', $object->toString()), 0, 24) : $this->name;
    }

    /**
     * What the activity brings the member on this board in the period: the measure's amount and each tie-break's.
     */
    public function contribution(Activity $activity, Period $period): Contribution
    {
        return new Contribution(
            $this->key($this->perObject ? $activity->object : null),
            $period->code,
            $activity->member,
            $activity->key->value,
            $this->measure->amountFor($activity),
            $activity->occurredAt,
            array_map(static fn (TieBreak $tieBreak): int => $tieBreak->amountFor($activity), $this->tieBreaks),
        );
    }

    /**
     * The score once the contribution is added to it; null when the score must be counted again from every
     * contribution, for a gesture on an earlier day than the score's on a board that counts days.
     */
    public function add(?Score $score, Contribution $contribution): ?Score
    {
        $added = $this->measure->add($score, $contribution, $this->schedule->timezone);

        if (null === $added) {
            return null;
        }

        $tieBreaks = [];

        foreach ($this->tieBreaks as $index => $tieBreak) {
            $tieBreaks[] = $tieBreak->aggregate->gather($score->tieBreaks[$index] ?? null, $contribution->tieBreaks[$index] ?? 0);
        }

        return new Score($contribution->board, $contribution->period, $contribution->member, $added[0], $added[1], $tieBreaks);
    }

    /**
     * The score the contributions make, counted again from nothing.
     *
     * @param non-empty-list<Contribution> $contributions
     */
    public function recount(array $contributions): Score
    {
        [$value, $reachedAt] = $this->measure->recount($contributions, $this->schedule->timezone);
        $tieBreaks = [];

        foreach ($this->tieBreaks as $index => $tieBreak) {
            $gathered = null;

            foreach ($contributions as $contribution) {
                $gathered = $tieBreak->aggregate->gather($gathered, $contribution->tieBreaks[$index] ?? 0);
            }

            $tieBreaks[] = $gathered;
        }

        $first = $contributions[0];

        return new Score($first->board, $first->period, $first->member, $value, $reachedAt, $tieBreaks);
    }

    /**
     * The first moment a gesture still counts on a board ranked over a sliding window at that moment: midnight, in the
     * board's timezone, of the first of its days, today included; null on another board.
     */
    public function windowStart(DateTimeImmutable $now): ?DateTimeImmutable
    {
        if (0 === $this->window) {
            return null;
        }

        return $now->setTimezone($this->schedule->timezone)->setTime(0, 0)->modify(\sprintf('-%d days', $this->window - 1));
    }
}
