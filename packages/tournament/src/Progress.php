<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use Tilttek\Tournament\Elimination\Bracket;
use Tilttek\Tournament\Elimination\BracketState;
use Tilttek\Tournament\Elimination\Slot;
use Tilttek\Tournament\Event\TournamentFinished;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Exception\RoundRefused;
use Tilttek\Tournament\RoundRobin\BergerTable;
use Tilttek\Tournament\RoundRobin\Pools;
use Tilttek\Tournament\Standings\Standing;
use Tilttek\Tournament\Standings\Standings;
use Tilttek\Tournament\Standings\StandingsCalculator;
use Tilttek\Tournament\Swiss\SwissPairer;
use Tilttek\Tournament\Tiebreak\Tiebreakers;

/**
 * What a tournament's state gives, read once for one call: the results that count, the participants of each stage, the
 * stage under way, its next round, its standings and its bracket. It lives as long as the call that made it.
 *
 * @internal
 */
final readonly class Progress
{
    /** @var array<string, Result> the results that count, by the key of the table */
    public array $results;

    public function __construct(
        public TournamentState $state,
        private Tiebreakers $tiebreakers,
        private SwissPairer $swiss,
    ) {
        $this->results = $this->effective($state);
    }

    /**
     * The next round of the stage under way.
     *
     * @throws RoundRefused when the last round has results missing or conflicts, or the tournament is over
     */
    public function next(): Round
    {
        if ($this->state->isOver()) {
            throw RoundRefused::because('The tournament is over.');
        }

        $last = $this->state->rounds[\count($this->state->rounds) - 1] ?? null;

        if (null !== $last && !$this->isComplete($last)) {
            throw RoundRefused::because(\sprintf('The round %d of the stage %s has results missing or conflicts to settle.', $last->number, $last->stage));
        }

        [$stage, $index] = $this->current();

        return $this->roundOf($stage, $index) ?? throw RoundRefused::because('The tournament has no round left to play.');
    }

    /**
     * The first stage not over, with its place; the last one once every stage is over.
     *
     * @return array{Stage, int}
     */
    public function current(): array
    {
        $stages = $this->state->tournament->stages;

        foreach ($stages as $index => $stage) {
            if (!$this->isStageOver($stage, $index)) {
                return [$stage, $index];
            }
        }

        $last = \count($stages) - 1;

        return [$stages[$last], $last];
    }

    private function isStageOver(Stage $stage, int $index): bool
    {
        $participants = $this->participantsOf($index);

        return [] !== $participants && $this->isPlayed($stage, $index, $participants);
    }

    /**
     * @param list<string> $participants
     */
    private function isPlayed(Stage $stage, int $index, array $participants): bool
    {
        if (StageFormat::SingleElimination === $stage->format) {
            return null !== $this->bracketState($index)->champion();
        }

        $rounds = $this->state->roundsOf($stage->id);
        $expected = StageFormat::Swiss === $stage->format ? $stage->rounds : \count(self::poolRounds($stage, $participants));

        if (\count($rounds) < $expected) {
            return false;
        }

        return array_all($rounds, fn (Round $round): bool => $this->isComplete($round));
    }

    /**
     * Whether every table of the round with two participants has a result that counts.
     */
    private function isComplete(Round $round): bool
    {
        return array_all($round->pairings, fn (Pairing $pairing): bool => $pairing->isBye() || isset($this->results[$round->key($pairing->table)]));
    }

    /**
     * The participants of a stage, in their order: the initial order for the first, the qualifiers of the stage before
     * for the others, by rank, the pools taken in turn at each rank. None while the stage before is not over.
     *
     * @return list<string>
     */
    public function participantsOf(int $index): array
    {
        if (0 === $index) {
            return $this->state->order;
        }

        $before = $index - 1;
        $previous = $this->state->tournament->stages[$before] ?? null;

        if (null === $previous || !$this->isStageOver($previous, $before)) {
            return [];
        }

        return array_values($this->qualifiers($previous, $before));
    }

    /**
     * The rounds of a round robin, the pools played side by side: round n has the tables of round n of each pool,
     * numbered on from one pool to the next.
     *
     * @param list<string> $participants
     *
     * @return list<list<Pairing>>
     */
    public static function poolRounds(Stage $stage, array $participants): array
    {
        $pools = $stage->pools > 1 ? Pools::snake($participants, $stage->pools) : ['' => $participants];
        $rounds = [];

        foreach ($pools as $pool => $members) {
            foreach (BergerTable::rounds($members, $stage->legs, '' === $pool ? null : $pool) as $number => $pairings) {
                foreach ($pairings as $pairing) {
                    $rounds[$number][] = $pairing->at(\count($rounds[$number] ?? []) + 1);
                }
            }
        }

        return array_values($rounds);
    }

    private function recordsOf(Stage $stage, int $index): Records
    {
        return Records::build($this->participantsOf($index), $this->state->roundsOf($stage->id), $this->results, $this->state->tournament->scoring);
    }

    /**
     * The standings of a stage, or of one of its pools. An elimination ranks by the round each participant reached,
     * then by seed.
     *
     * @throws InvalidTournament when the stage has no such pool
     */
    public function standingsOf(Stage $stage, int $index, ?string $pool = null): Standings
    {
        $members = $this->participantsOf($index);
        $records = $this->recordsOf($stage, $index);
        $withdrawn = $this->state->withdrawnSet();

        if (StageFormat::SingleElimination === $stage->format) {
            $reached = $this->bracketState($index)->reached();
            $order = array_flip($members);
            $depth = static fn (string $participant): int => $reached[$participant] ?? 0;
            usort($members, static fn (string $a, string $b): int => [$depth($b), $order[$a]] <=> [$depth($a), $order[$b]]);
            $standings = [];

            foreach ($members as $position => $participant) {
                $standings[] = new Standing($participant, $position + 1, $records->points($participant), withdrawn: isset($withdrawn[$participant]));
            }

            return new Standings($standings);
        }

        if (null !== $pool) {
            $pools = $stage->pools > 1 ? Pools::snake($members, $stage->pools) : [];
            $members = $pools[$pool] ?? throw InvalidTournament::because(\sprintf('The stage %s has no pool %s.', $stage->id, $pool));
        }

        $calculator = new StandingsCalculator($this->tiebreakers->get($this->state->tournament->tiebreakers), $this->state->tournament->seed);

        return $calculator->compute($members, $records, $withdrawn);
    }

    /**
     * The bracket of an elimination stage, from the initial order for the first stage, from the ranks of the stage
     * before for another.
     */
    public function bracketOf(int $index): Bracket
    {
        $previous = $this->state->tournament->stages[$index - 1] ?? null;

        if (null === $previous) {
            return Bracket::seeded(array_map(Slot::participant(...), $this->state->order));
        }

        $slots = [];

        for ($rank = 1; $rank <= $previous->qualifiers; ++$rank) {
            foreach ($this->poolNames($previous) as $from) {
                $slots[] = Slot::qualifier($from, $rank);
            }
        }

        return Bracket::seeded($slots);
    }

    /**
     * The bracket of an elimination stage filled with the qualifiers known, the results and the withdrawals.
     */
    public function bracketState(int $index): BracketState
    {
        $stage = $this->state->tournament->stages[$index]->id ?? '';
        $before = $index - 1;
        $previous = $this->state->tournament->stages[$before] ?? null;
        $qualifiers = null !== $previous && $this->isStageOver($previous, $before) ? $this->qualifiers($previous, $before) : [];
        $results = [];

        foreach ($this->state->roundsOf($stage) as $round) {
            foreach ($round->pairings as $pairing) {
                $result = $this->results[$round->key($pairing->table)] ?? null;

                if (null !== $result) {
                    $results[$pairing->table] = $result;
                }
            }
        }

        return BracketState::of($this->bracketOf($index), $qualifiers, $results, $this->state->withdrawnSet());
    }

    /**
     * The next round of a stage; none when the stage has played them all.
     */
    private function roundOf(Stage $stage, int $index): ?Round
    {
        $number = \count($this->state->roundsOf($stage->id)) + 1;

        if (StageFormat::SingleElimination === $stage->format) {
            $pairings = $this->bracketState($index)->pairings($number);

            return [] === $pairings ? null : new Round($stage->id, $number, $pairings);
        }

        $participants = $this->participantsOf($index);

        if (StageFormat::RoundRobin === $stage->format) {
            $rounds = self::poolRounds($stage, $participants);

            return isset($rounds[$number - 1]) ? new Round($stage->id, $number, $rounds[$number - 1]) : null;
        }

        if ($number > $stage->rounds) {
            return null;
        }

        $active = array_values(array_diff($participants, $this->state->withdrawn));

        return new Round($stage->id, $number, $this->swiss->pair($active, $this->recordsOf($stage, $index)));
    }

    /**
     * The tournament with its podium when its last stage is over, and the event when the podium is new.
     *
     * @return array{TournamentState, list<TournamentFinished>}
     */
    public function settle(): array
    {
        $stages = $this->state->tournament->stages;
        $index = \count($stages) - 1;
        $stage = $stages[$index];

        if (!$this->isStageOver($stage, $index)) {
            return [$this->state, []];
        }

        if (StageFormat::SingleElimination === $stage->format) {
            $podium = $this->bracketState($index)->podium();
        } else {
            $standings = array_filter($this->standingsOf($stage, $index)->standings, static fn (Standing $standing): bool => !$standing->withdrawn);
            $podium = array_map(static fn (Standing $standing): string => $standing->participant, \array_slice($standings, 0, 3));
        }

        if ([] === $podium || $podium === $this->state->podium) {
            return [$this->state, []];
        }

        return [$this->state->with(podium: $podium), [new TournamentFinished($this->state->tournament->id, $podium[0], $podium)]];
    }

    /**
     * The qualifiers of a stage over, by the label of their place in the next stage.
     *
     * @return array<string, string>
     */
    private function qualifiers(Stage $stage, int $index): array
    {
        $byPool = [];

        foreach ($this->poolNames($stage) as $pool => $from) {
            $byPool[$from] = $this->standingsOf($stage, $index, '' === $pool ? null : $pool)->qualifiers($stage->qualifiers);
        }

        $qualifiers = [];

        for ($rank = 1; $rank <= $stage->qualifiers; ++$rank) {
            foreach ($byPool as $from => $ranked) {
                if (isset($ranked[$rank - 1])) {
                    $qualifiers[Slot::qualifier($from, $rank)->label()] = $ranked[$rank - 1];
                }
            }
        }

        return $qualifiers;
    }

    /**
     * The name of each pool of a stage as the next stage refers to it, `stage/A`, by letter; the stage itself under an
     * empty key when it has one pool.
     *
     * @return array<string, string>
     */
    private function poolNames(Stage $stage): array
    {
        if (1 === $stage->pools) {
            return ['' => $stage->id];
        }

        $names = [];

        foreach (range('A', 'Z') as $position => $letter) {
            if ($position < $stage->pools) {
                $names[$letter] = $stage->id.'/'.$letter;
            }
        }

        return $names;
    }

    /**
     * The results that count: those kept, and a forfeit for each table left without one where a participant withdrew;
     * a table in conflict has none.
     *
     * @return array<string, Result>
     */
    private function effective(TournamentState $state): array
    {
        $results = array_map(static fn (Submission $submission): Result => $submission->result, $state->results);
        $withdrawn = $state->withdrawnSet();

        foreach ($state->rounds as $round) {
            foreach ($round->pairings as $pairing) {
                $key = $round->key($pairing->table);

                if (null === $pairing->second || isset($results[$key]) || isset($state->conflicts[$key])) {
                    continue;
                }

                $outcome = match (true) {
                    isset($withdrawn[$pairing->first], $withdrawn[$pairing->second]) => Outcome::BothForfeit,
                    isset($withdrawn[$pairing->first]) => Outcome::FirstForfeits,
                    isset($withdrawn[$pairing->second]) => Outcome::SecondForfeits,
                    default => null,
                };

                if (null !== $outcome) {
                    $results[$key] = new Result($outcome);
                }
            }
        }

        return $results;
    }
}
